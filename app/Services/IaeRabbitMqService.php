<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaeRabbitMqService
{
    private string $baseUrl  = 'https://iae-sso.virtualfri.id';
    private string $exchange = 'iae.central.exchange';
    private string $teamId   = 'TEAM-11';

    /**
     * Publish event JSON ke RabbitMQ dosen via REST API.
     * Exchange wajib: iae.central.exchange
     *
     * @param string $token      Bearer token dari SSO
     * @param string $eventName  Nama event (e.g. "room.assigned", "room.created")
     * @param array  $eventData  Payload event
     * @return bool
     */
    public function publish(string $token, string $eventName, array $eventData): bool
    {
        $payload = [
            'exchange'    => $this->exchange,
            'routing_key' => $eventName,
            'message'     => [
                'event'     => $eventName,
                'timestamp' => now()->toISOString(),
                'team_id'   => $this->teamId,
                'data'      => $eventData,
            ],
        ];

        Log::info('[IAE-RABBITMQ] Publishing event', [
            'event'    => $eventName,
            'exchange' => $this->exchange,
        ]);

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/api/v1/messages/publish", $payload);

        if ($response->failed()) {
            Log::error('[IAE-RABBITMQ] Publish gagal', [
                'status'   => $response->status(),
                'response' => $response->body(),
                'event'    => $eventName,
            ]);
            // Tidak throw exception agar tidak block response utama
            return false;
        }

        Log::info('[IAE-RABBITMQ] Event berhasil dipublish', [
            'event'    => $eventName,
            'response' => $response->json(),
        ]);

        return true;
    }

    /**
     * Publish event room.created
     */
    public function publishRoomCreated(string $token, array $room): bool
    {
        return $this->publish($token, 'room.created', [
            'room_id'        => $room['id'],
            'room_number'    => $room['room_number'],
            'type'           => $room['type'],
            'floor'          => $room['floor'],
            'capacity'       => $room['capacity'],
            'price_per_night'=> $room['price_per_night'],
            'status'         => $room['status'] ?? 'available',
            'created_by'     => 'warga38@ktp.iae.id',
        ]);
    }

    /**
     * Publish event room.assigned
     */
    public function publishRoomAssigned(string $token, array $room, string $guestName, string $reservationId): bool
    {
        return $this->publish($token, 'room.assigned', [
            'room_id'        => $room['id'],
            'room_number'    => $room['room_number'],
            'type'           => $room['type'],
            'price_per_night'=> $room['price_per_night'],
            'guest_name'     => $guestName,
            'reservation_id' => $reservationId,
            'assigned_at'    => now()->toISOString(),
            'assigned_by'    => 'warga38@ktp.iae.id',
        ]);
    }
}
