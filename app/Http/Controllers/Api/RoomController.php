<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoomRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomStatusRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\IaeSsoService;
use App\Services\IaeSoapService;
use App\Services\IaeRabbitMqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

// ── Schema: Room ─────────────────────────────────────────────────────────────
#[OA\Schema(
    schema: 'Room',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'room_number', type: 'string', example: '101'),
        new OA\Property(property: 'type', type: 'string', example: 'deluxe'),
        new OA\Property(property: 'floor', type: 'integer', example: 1),
        new OA\Property(property: 'capacity', type: 'integer', example: 2),
        new OA\Property(property: 'price_per_night', type: 'number', example: 500000),
        new OA\Property(property: 'status', type: 'string', example: 'available'),
        new OA\Property(property: 'description', type: 'string', example: 'Kamar deluxe dengan view kolam renang'),
        new OA\Property(property: 'facilities', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'created_at', type: 'string', example: '2024-01-01T00:00:00+07:00'),
        new OA\Property(property: 'updated_at', type: 'string', example: '2024-01-01T00:00:00+07:00'),
    ]
)]

// ── Schema: ApiMeta ──────────────────────────────────────────────────────────
#[OA\Schema(
    schema: 'RoomApiMeta',
    properties: [
        new OA\Property(property: 'service_name', type: 'string', example: 'Room-Catalog-Service'),
        new OA\Property(property: 'api_version', type: 'string', example: 'v1'),
    ]
)]

// ── Schema: RoomCollectionResponse ───────────────────────────────────────────
#[OA\Schema(
    schema: 'RoomCollectionResponse',
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'message', type: 'string', example: 'Data retrieved successfully'),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Room')),
        new OA\Property(property: 'meta', ref: '#/components/schemas/RoomApiMeta'),
    ]
)]

// ── Schema: RoomSingleResponse ───────────────────────────────────────────────
#[OA\Schema(
    schema: 'RoomSingleResponse',
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'message', type: 'string', example: 'Data retrieved successfully'),
        new OA\Property(property: 'data', ref: '#/components/schemas/Room'),
        new OA\Property(property: 'meta', ref: '#/components/schemas/RoomApiMeta'),
    ]
)]

// ── Schema: RoomErrorResponse ────────────────────────────────────────────────
#[OA\Schema(
    schema: 'RoomErrorResponse',
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'error'),
        new OA\Property(property: 'message', type: 'string', example: 'Room not found'),
        new OA\Property(property: 'errors', nullable: true, example: null),
    ]
)]

#[OA\Info(
    version: '1.0.0',
    description: 'Service Katalog & Kamar - Smart Hospitality IAE Kelompok 11',
    title: 'Room Catalog Service API',
    contact: new OA\Contact(email: 'admin@hotel.com')
)]

#[OA\SecurityScheme(
    securityScheme: 'X-IAE-KEY',
    type: 'apiKey',
    name: 'X-IAE-KEY',
    in: 'header'
)]

#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]

#[OA\Server(url: '/', description: 'Room Catalog Service')]

#[OA\Tag(name: 'Rooms', description: 'API Endpoints untuk manajemen kamar hotel')]
class RoomController extends Controller
{
    public function __construct(
        private IaeSsoService     $sso,
        private IaeSoapService    $soap,
        private IaeRabbitMqService $mq,
    ) {}

    // =========================================================================
    // GET /api/v1/rooms
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/rooms',
        summary: 'Mengambil daftar seluruh kamar',
        security: [['X-IAE-KEY' => []], ['bearerAuth' => []]],
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['available', 'occupied', 'maintenance'])
            ),
            new OA\Parameter(name: 'type', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['standard', 'deluxe', 'suite', 'presidential'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar kamar berhasil diambil',
                content: new OA\JsonContent(ref: '#/components/schemas/RoomCollectionResponse')
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Room::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $rooms = $query->orderByDesc('created_at')->get();

        return $this->successResponse(
            RoomResource::collection($rooms),
            'Data retrieved successfully',
            $this->apiMeta(['total' => $rooms->count()])
        );
    }

    // =========================================================================
    // GET /api/v1/rooms/{id}
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/rooms/{id}',
        summary: 'Mengambil data spesifik kamar berdasarkan ID',
        security: [['X-IAE-KEY' => []], ['bearerAuth' => []]],
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data kamar berhasil diambil',
                content: new OA\JsonContent(ref: '#/components/schemas/RoomSingleResponse')
            ),
            new OA\Response(response: 404, description: 'Kamar tidak ditemukan'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $room = Room::find($id);

        if (! $room) {
            return $this->errorResponse('Room not found', 404, null);
        }

        return $this->successResponse(
            new RoomResource($room),
            'Data retrieved successfully',
            $this->apiMeta()
        );
    }

    // =========================================================================
    // POST /api/v1/rooms
    // Transaksi kritis #1: Room Created
    // Alur: Validasi → Simpan DB → SSO Login → SOAP Audit → RabbitMQ Publish
    // =========================================================================

    #[OA\Post(
        path: '/api/v1/rooms',
        summary: 'Menambah data kamar baru (triggers SSO + SOAP audit + RabbitMQ)',
        security: [['X-IAE-KEY' => []], ['bearerAuth' => []]],
        tags: ['Rooms'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['room_number', 'type', 'floor', 'capacity', 'price_per_night'],
                properties: [
                    new OA\Property(property: 'room_number', type: 'string', example: '101'),
                    new OA\Property(property: 'type', type: 'string', enum: ['standard', 'deluxe', 'suite', 'presidential']),
                    new OA\Property(property: 'floor', type: 'integer', example: 1),
                    new OA\Property(property: 'capacity', type: 'integer', example: 2),
                    new OA\Property(property: 'price_per_night', type: 'number', example: 500000),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'facilities', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Kamar berhasil ditambahkan'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function store(StoreRoomRequest $request): JsonResponse
    {
        // 1. Simpan room ke DB lokal
        $room = Room::create($request->validated());

        // 2. Integrasi Central Infrastructure (SSO → SOAP → RabbitMQ)
        $integrationResult = $this->triggerCentralInfrastructure(
            activityName: 'RoomCreated',
            logData: [
                'room_id'         => $room->id,
                'room_number'     => $room->room_number,
                'type'            => $room->type,
                'floor'           => $room->floor,
                'capacity'        => $room->capacity,
                'price_per_night' => $room->price_per_night,
                'status'          => $room->status,
                'action'          => 'room_created',
                'justification'   => 'Penambahan aset kamar baru ke inventaris hotel merupakan transaksi kritis karena mempengaruhi ketersediaan kamar dan kapasitas operasional.',
            ],
            mqPublisher: fn(string $token) => $this->mq->publishRoomCreated($token, $room->toArray())
        );

        return $this->successResponse(
            new RoomResource($room),
            'Room created successfully',
            $this->apiMeta(['iae_integration' => $integrationResult]),
            201
        );
    }

    // =========================================================================
    // PUT /api/v1/rooms/{id}/status
    // =========================================================================

    #[OA\Put(
        path: '/api/v1/rooms/{id}/status',
        summary: 'Mengubah status kamar',
        security: [['X-IAE-KEY' => []], ['bearerAuth' => []]],
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status kamar berhasil diperbarui'),
            new OA\Response(response: 404, description: 'Kamar tidak ditemukan'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function updateStatus(UpdateRoomStatusRequest $request, int $id): JsonResponse
    {
        $room = Room::find($id);

        if (! $room) {
            return $this->errorResponse('Room not found', 404, null);
        }

        $room->update($request->validated());

        return $this->successResponse(
            new RoomResource($room->fresh()),
            'Room status updated successfully',
            $this->apiMeta()
        );
    }

    // =========================================================================
    // POST /api/v1/rooms/{id}/assign
    // Transaksi kritis #2 (UTAMA): Room Assigned
    // Alur: Validasi → Update status DB → SSO Login → SOAP Audit → RabbitMQ Publish
    // =========================================================================

    #[OA\Post(
        path: '/api/v1/rooms/{id}/assign',
        summary: 'Menetapkan kamar ke tamu (triggers SSO + SOAP audit + RabbitMQ)',
        security: [['X-IAE-KEY' => []], ['bearerAuth' => []]],
        tags: ['Rooms'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['guest_name', 'reservation_id'],
                properties: [
                    new OA\Property(property: 'guest_name', type: 'string', example: 'Budi Santoso'),
                    new OA\Property(property: 'reservation_id', type: 'string', example: 'RES-2024-001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kamar berhasil di-assign'),
            new OA\Response(response: 409, description: 'Kamar sudah terisi'),
            new OA\Response(response: 404, description: 'Kamar tidak ditemukan'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function assign(AssignRoomRequest $request, int $id): JsonResponse
    {
        $room = Room::find($id);

        if (! $room) {
            return $this->errorResponse('Room not found', 404, null);
        }

        if ($room->status !== 'available') {
            return $this->errorResponse(
                'Room is not available. Current status: ' . $room->status,
                409,
                null
            );
        }

        $validated  = $request->validated();
        $assignedAt = now()->toISOString();

        // 1. Update status kamar → occupied
        $room->update(['status' => 'occupied']);

        // 2. Integrasi Central Infrastructure (SSO → SOAP → RabbitMQ)
        $integrationResult = $this->triggerCentralInfrastructure(
            activityName: 'RoomAssigned',
            logData: [
                'room_id'         => $room->id,
                'room_number'     => $room->room_number,
                'type'            => $room->type,
                'floor'           => $room->floor,
                'price_per_night' => $room->price_per_night,
                'guest_name'      => $validated['guest_name'],
                'reservation_id'  => $validated['reservation_id'],
                'assigned_at'     => $assignedAt,
                'action'          => 'room_assigned',
                'justification'   => 'Assign kamar ke tamu adalah transaksi kritis: mengubah status ketersediaan kamar (state-changing) dan melibatkan nilai finansial (harga per malam). Wajib diaudit untuk akuntabilitas operasional hotel.',
            ],
            mqPublisher: fn(string $token) => $this->mq->publishRoomAssigned(
                $token,
                $room->toArray(),
                $validated['guest_name'],
                $validated['reservation_id']
            )
        );

        return $this->successResponse(
            [
                'room'           => new RoomResource($room->fresh()),
                'guest_name'     => $validated['guest_name'],
                'reservation_id' => $validated['reservation_id'],
                'assigned_at'    => $assignedAt,
            ],
            'Room successfully assigned to guest',
            $this->apiMeta(['iae_integration' => $integrationResult])
        );
    }

    // =========================================================================
    // Helper: Orkestrasi 3 lapis SSO → SOAP → RabbitMQ
    // =========================================================================

    /**
     * Jalankan orkestrasi 3 lapis secara berurutan:
     * 1. Login SSO Dosen → dapat JWT token
     * 2. Kirim SOAP Audit → dapat ReceiptNumber
     * 3. Broadcast Event ke RabbitMQ
     *
     * Error di SOAP/MQ tidak akan membatalkan transaksi utama (non-blocking),
     * tapi tetap di-log dan dikembalikan di response meta.
     */
    private function triggerCentralInfrastructure(
        string   $activityName,
        array    $logData,
        callable $mqPublisher
    ): array {
        $result = [
            'sso'      => ['status' => 'pending'],
            'soap'     => ['status' => 'pending'],
            'rabbitmq' => ['status' => 'pending'],
        ];

        try {
            // LAPIS 1: SSO Login
            $token = $this->sso->getM2MToken();
            $this->sso->mapUserToLocalRole($token);
            $result['sso'] = ['status' => 'success'];

            // LAPIS 2: SOAP Audit
            try {
                $receiptNumber = $this->soap->sendAudit($token, $activityName, $logData);
                $result['soap'] = [
                    'status'         => 'success',
                    'receipt_number' => $receiptNumber,
                ];
            } catch (\Throwable $e) {
                Log::error('[IAE] SOAP audit gagal', ['error' => $e->getMessage()]);
                $result['soap'] = ['status' => 'error', 'message' => $e->getMessage()];
            }

            // LAPIS 3: RabbitMQ Publish
            try {
                $published = $mqPublisher($token);
                $result['rabbitmq'] = ['status' => $published ? 'success' : 'error'];
            } catch (\Throwable $e) {
                Log::error('[IAE] RabbitMQ publish gagal', ['error' => $e->getMessage()]);
                $result['rabbitmq'] = ['status' => 'error', 'message' => $e->getMessage()];
            }

        } catch (\Throwable $e) {
            // SSO gagal = semua lapis gagal
            Log::error('[IAE] SSO login gagal', ['error' => $e->getMessage()]);
            $result['sso']      = ['status' => 'error', 'message' => $e->getMessage()];
            $result['soap']     = ['status' => 'skipped'];
            $result['rabbitmq'] = ['status' => 'skipped'];
        }

        return $result;
    }

    /**
     * Metadata standar API untuk response.
     */
    private function apiMeta(array $extra = []): array
    {
        return array_merge([
            'service_name' => 'Room-Catalog-Service',
            'api_version'  => 'v1',
        ], $extra);
    }
}
