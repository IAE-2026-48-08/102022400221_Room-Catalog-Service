<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IaeSsoService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = env('CENTRAL_SERVER_URL');
        $this->apiKey  = env('CENTRAL_TEAM_API_KEY');
    }

    /**
     * Login M2M pakai API Key, return JWT token.
     * Di-cache sesuai TTL dari response (default 1 jam).
     */
    public function getM2MToken(): string
    {
        $cached = Cache::get('iae_m2m_token');
        if ($cached) {
            return $cached;
        }

        $response = Http::post("{$this->baseUrl}/api/v1/auth/token", [
            'api_key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            Log::error('[IAE-SSO] M2M login gagal', ['response' => $response->body()]);
            throw new \RuntimeException('IAE SSO M2M login failed: ' . $response->body());
        }

        $token = $response->json('token')
            ?? throw new \RuntimeException('Token tidak ditemukan di response SSO');

        $ttl = $response->json('expires_in', 3600);
        Cache::put('iae_m2m_token', $token, $ttl);

        Log::info('[IAE-SSO] M2M token berhasil didapat', [
            'app_name' => $response->json('app.name'),
            'team'     => $response->json('app.team'),
        ]);

        return $token;
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

        $email = $payload['email'] ?? ($payload['app']['client_id'] ?? 'iae-m2m@internal') . '@m2m.iae.internal';
        $name  = $payload['name'] ?? $payload['app']['name'] ?? $payload['sub'] ?? 'IAE User';

        // Upsert user lokal
        $user = \App\Models\User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => bcrypt('iae-sso-user-' . time()),
                'role'     => 'operator',
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
