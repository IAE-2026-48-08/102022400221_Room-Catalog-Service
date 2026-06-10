<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IaeSsoService
{
    private string $baseUrl = 'https://iae-sso.virtualfri.id';
    private string $apiKey  = 'KEY-MHS-292';
    private string $email   = 'warga38@ktp.iae.id';
    private string $password = 'KtpDigital2026!';

    /**
     * Login M2M pakai API Key, return JWT token.
     * Di-cache 50 menit biar tidak login terus tiap request.
     */
    public function getM2MToken(): string
    {
        return Cache::remember('iae_m2m_token', 3000, function () {
            $response = Http::post("{$this->baseUrl}/api/v1/auth/token", [
                'api_key' => $this->apiKey,
            ]);

            if ($response->failed()) {
                Log::error('[IAE-SSO] M2M login gagal', ['response' => $response->body()]);
                throw new \RuntimeException('IAE SSO M2M login failed: ' . $response->body());
            }

            $token = $response->json('token') ?? $response->json('access_token');

            if (!$token) {
                Log::error('[IAE-SSO] Token tidak ditemukan di response', ['body' => $response->json()]);
                throw new \RuntimeException('IAE SSO: token tidak ada di response');
            }

            Log::info('[IAE-SSO] M2M token berhasil didapat');
            return $token;
        });
    }

    /**
     * Login end-user (warga) pakai email + password, return JWT token.
     * Di-cache 50 menit.
     */
    public function getUserToken(): string
    {
        return Cache::remember('iae_user_token', 3000, function () {
            $response = Http::post("{$this->baseUrl}/api/v1/auth/token", [
                'email'    => $this->email,
                'password' => $this->password,
            ]);

            if ($response->failed()) {
                Log::error('[IAE-SSO] User login gagal', ['response' => $response->body()]);
                throw new \RuntimeException('IAE SSO user login failed: ' . $response->body());
            }

            $token = $response->json('token') ?? $response->json('access_token');

            if (!$token) {
                throw new \RuntimeException('IAE SSO: token tidak ada di response');
            }

            Log::info('[IAE-SSO] User token berhasil didapat');
            return $token;
        });
    }

    /**
     * Decode payload dari JWT token (tanpa verifikasi signature).
     * Untuk ambil name, email dari token.
     */
    public function decodeJwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(str_pad(
            strtr($parts[1], '-_', '+/'),
            strlen($parts[1]) % 4,
            '='
        ));

        return json_decode($payload, true) ?? [];
    }

    /**
     * Map user SSO ke role lokal berdasarkan payload JWT.
     * Simpan ke tabel users lokal kalau belum ada.
     */
    public function mapUserToLocalRole(string $token): array
    {
        $payload = $this->decodeJwtPayload($token);

        $email = $payload['email'] ?? $this->email;
        $name  = $payload['name']  ?? $payload['sub'] ?? 'IAE User';

        // Upsert user lokal
        $user = \App\Models\User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => bcrypt('iae-sso-user-' . time()),
                'role'     => 'operator', // role lokal default untuk SSO user
            ]
        );

        Log::info('[IAE-SSO] User mapped ke role lokal', [
            'email' => $email,
            'role'  => $user->role ?? 'operator',
        ]);

        return [
            'user'    => $user,
            'payload' => $payload,
        ];
    }
}
