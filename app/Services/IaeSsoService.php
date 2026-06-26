<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IaeSsoService
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $email;
    private ?string $password;

    public function __construct()
    {
        // Support both old branch vars (CENTRAL_*) and new branch vars (IAE_SSO_*)
        $this->baseUrl  = env('IAE_SSO_BASE_URL', env('CENTRAL_SERVER_URL', ''));
        $this->apiKey   = env('CENTRAL_TEAM_API_KEY') ?: env('IAE_API_KEY') ?: null;
        $this->email    = env('IAE_SSO_EMAIL') ?: null;
        $this->password = env('IAE_SSO_PASSWORD') ?: null;
    }

    /**
     * Obtain JWT token from IAE SSO central server.
     * Tries M2M api_key first; falls back to email+password (End-User SSO).
     * Di-cache sesuai TTL dari response (default 1 jam).
     */
    public function getM2MToken(): string
    {
        $cached = Cache::get('iae_m2m_token');
        if ($cached) {
            return $cached;
        }

        // Build payload: prefer api_key (M2M), fall back to email+password
        if ($this->apiKey) {
            $payload = ['api_key' => $this->apiKey];
            $authMode = 'M2M api_key';
        } elseif ($this->email && $this->password) {
            $payload = ['email' => $this->email, 'password' => $this->password];
            $authMode = 'email+password';
        } else {
            throw new \RuntimeException('IAE SSO: No credentials configured. Set CENTRAL_TEAM_API_KEY or IAE_SSO_EMAIL+IAE_SSO_PASSWORD.');
        }

        Log::info('[IAE-SSO] Attempting login', ['mode' => $authMode, 'url' => $this->baseUrl]);

        $response = Http::post("{$this->baseUrl}/api/v1/auth/token", $payload);

        // If api_key was rejected, retry with email+password
        if ($response->failed() && $this->apiKey && $this->email && $this->password) {
            Log::warning('[IAE-SSO] api_key rejected, retrying with email+password', [
                'response' => $response->body(),
            ]);
            $response = Http::post("{$this->baseUrl}/api/v1/auth/token", [
                'email'    => $this->email,
                'password' => $this->password,
            ]);
            $authMode = 'email+password (fallback)';
        }

        if ($response->failed()) {
            Log::error('[IAE-SSO] Login gagal', ['response' => $response->body(), 'mode' => $authMode]);
            throw new \RuntimeException('IAE SSO login failed: ' . $response->body());
        }

        $token = $response->json('token')
            ?? throw new \RuntimeException('Token tidak ditemukan di response SSO');

        $ttl = $response->json('expires_in', 3600);
        Cache::put('iae_m2m_token', $token, $ttl);

        Log::info('[IAE-SSO] Token berhasil didapat', [
            'mode'     => $authMode,
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
