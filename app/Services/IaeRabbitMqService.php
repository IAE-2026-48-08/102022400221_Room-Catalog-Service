<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaeRabbitMqService
{
    private string $httpUrl;
    private string $exchange;
    private string $teamId;

    public function __construct()
    {
        $this->httpUrl  = env('RABBITMQ_HTTP_URL');
        $this->exchange = env('RABBITMQ_EXCHANGE', 'iae.central.exchange');
        $this->teamId   = env('CENTRAL_TEAM_API_KEY');
    }

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

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->post($this->httpUrl, $payload);

        Log::info('[AMQP-HTTP] Publish result', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            Log::error('[IAE-RABBITMQ] Publish gagal', [
                'status'   => $response->status(),
                'response' => $response->body(),
                'event'    => $eventName,
            ]);
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
            'room_id'         => $room['id'],
            'room_number'     => $room['room_number'],
            'type'            => $room['type'],
            'floor'           => $room['floor'],
            'capacity'        => $room['capacity'],
            'price_per_night' => $room['price_per_night'],
            'status'          => $room['status'] ?? 'available',
            'activity_name'   => 'RoomCreated',
            'timestamp'       => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish event room.assigned
     */
    public function publishRoomAssigned(string $token, array $room, string $guestName, string $reservationId): bool
    {
        return $this->publish($token, 'room.assigned', [
            'room_id'         => $room['id'],
            'room_number'     => $room['room_number'],
            'type'            => $room['type'],
            'price_per_night' => $room['price_per_night'],
            'guest_name'      => $guestName,
            'reservation_id'  => $reservationId,
            'activity_name'   => 'RoomAssigned',
            'assigned_at'     => now()->toIso8601String(),
            'timestamp'       => now()->toIso8601String(),
        ]);
    }
}
