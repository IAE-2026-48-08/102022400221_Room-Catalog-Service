<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\IaeSsoService;
use App\Services\IaeSoapService;
use App\Services\IaeRabbitMqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Info(
 *     title="Room Catalog Service API",
 *     version="1.0.0",
 *     description="Service Katalog & Kamar - Smart Hospitality IAE Kelompok 11",
 *     @OA\Contact(email="admin@hotel.com")
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="X-IAE-KEY",
 *     type="apiKey",
 *     in="header",
 *     name="X-IAE-KEY"
 * )
 *
 * @OA\Server(url="/", description="Room Catalog Service")
 *
 * @OA\Schema(
 *     schema="Room",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="room_number", type="string", example="101"),
 *     @OA\Property(property="type", type="string", example="deluxe"),
 *     @OA\Property(property="floor", type="integer", example=1),
 *     @OA\Property(property="capacity", type="integer", example=2),
 *     @OA\Property(property="price_per_night", type="number", example=500000),
 *     @OA\Property(property="status", type="string", example="available"),
 *     @OA\Property(property="description", type="string", example="Kamar deluxe dengan view kolam renang"),
 *     @OA\Property(property="facilities", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", example="2024-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", example="2024-01-01T00:00:00.000000Z")
 * )
 */
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

    /**
     * @OA\Get(
     *     path="/api/v1/rooms",
     *     summary="Mengambil daftar seluruh kamar",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         @OA\Schema(type="string", enum={"available","occupied","maintenance"})),
     *     @OA\Parameter(name="type", in="query", required=false,
     *         @OA\Schema(type="string", enum={"standard","deluxe","suite","presidential"})),
     *     @OA\Response(response=200, description="Daftar kamar berhasil diambil")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Room::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $rooms = $query->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data retrieved successfully',
            'data'    => $rooms,
            'meta'    => [
                'service_name' => 'Room-Catalog-Service',
                'api_version'  => 'v1',
                'total'        => $rooms->count(),
            ],
        ]);
    }

    // =========================================================================
    // GET /api/v1/rooms/{id}
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/v1/rooms/{id}",
     *     summary="Mengambil data spesifik kamar berdasarkan ID",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Data kamar berhasil diambil"),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Room not found',
                'errors'  => null,
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data retrieved successfully',
            'data'    => $room,
            'meta'    => ['service_name' => 'Room-Catalog-Service', 'api_version' => 'v1'],
        ]);
    }

    // =========================================================================
    // POST /api/v1/rooms
    // Transaksi kritis #1: Room Created
    // Alur: Validasi → Simpan DB → SSO Login → SOAP Audit → RabbitMQ Publish
    // =========================================================================

    /**
     * @OA\Post(
     *     path="/api/v1/rooms",
     *     summary="Menambah data kamar baru (triggers SSO + SOAP audit + RabbitMQ)",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"room_number","type","floor","capacity","price_per_night"},
     *             @OA\Property(property="room_number", type="string", example="101"),
     *             @OA\Property(property="type", type="string", enum={"standard","deluxe","suite","presidential"}),
     *             @OA\Property(property="floor", type="integer", example=1),
     *             @OA\Property(property="capacity", type="integer", example=2),
     *             @OA\Property(property="price_per_night", type="number", example=500000),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="facilities", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Kamar berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'room_number'     => 'required|string|unique:rooms,room_number',
                'type'            => 'required|in:standard,deluxe,suite,presidential',
                'floor'           => 'required|integer|min:1',
                'capacity'        => 'required|integer|min:1',
                'price_per_night' => 'required|numeric|min:0',
                'description'     => 'nullable|string',
                'facilities'      => 'nullable|array',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        // 1. Simpan room ke DB lokal
        $room = Room::create($validated);

        // 2. Integrasi Central Infrastructure (SSO → SOAP → RabbitMQ)
        $integrationResult = $this->triggerCentralInfrastructure(
            activityName: 'RoomCreated',
            logData: [
                'room_id'        => $room->id,
                'room_number'    => $room->room_number,
                'type'           => $room->type,
                'floor'          => $room->floor,
                'capacity'       => $room->capacity,
                'price_per_night'=> $room->price_per_night,
                'status'         => $room->status,
                'action'         => 'room_created',
                'justification'  => 'Penambahan aset kamar baru ke inventaris hotel merupakan transaksi kritis karena mempengaruhi ketersediaan kamar dan kapasitas operasional.',
            ],
            mqPublisher: fn(string $token) => $this->mq->publishRoomCreated($token, $room->toArray())
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Room created successfully',
            'data'    => $room,
            'meta'    => [
                'service_name'        => 'Room-Catalog-Service',
                'api_version'         => 'v1',
                'iae_integration'     => $integrationResult,
            ],
        ], 201);
    }

    // =========================================================================
    // PUT /api/v1/rooms/{id}/status
    // =========================================================================

    /**
     * @OA\Put(
     *     path="/api/v1/rooms/{id}/status",
     *     summary="Mengubah status kamar",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"status"},
     *             @OA\Property(property="status", type="string",
     *                 enum={"available","occupied","maintenance"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status kamar berhasil diperbarui"),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan")
     * )
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Room not found',
                'errors'  => null,
            ], 404);
        }

        try {
            $validated = $request->validate([
                'status' => 'required|in:available,occupied,maintenance',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        $room->update(['status' => $validated['status']]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Room status updated successfully',
            'data'    => $room->fresh(),
            'meta'    => ['service_name' => 'Room-Catalog-Service', 'api_version' => 'v1'],
        ]);
    }

    // =========================================================================
    // POST /api/v1/rooms/{id}/assign
    // Transaksi kritis #2 (UTAMA): Room Assigned
    // Alur: Validasi → Update status DB → SSO Login → SOAP Audit → RabbitMQ Publish
    // =========================================================================

    /**
     * @OA\Post(
     *     path="/api/v1/rooms/{id}/assign",
     *     summary="Menetapkan kamar ke tamu (triggers SSO + SOAP audit + RabbitMQ)",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"guest_name","reservation_id"},
     *             @OA\Property(property="guest_name", type="string", example="Budi Santoso"),
     *             @OA\Property(property="reservation_id", type="string", example="RES-2024-001")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Kamar berhasil di-assign"),
     *     @OA\Response(response=409, description="Kamar sudah terisi"),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan")
     * )
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Room not found',
                'errors'  => null,
            ], 404);
        }

        if ($room->status !== 'available') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Room is not available. Current status: ' . $room->status,
                'errors'  => null,
            ], 409);
        }

        try {
            $validated = $request->validate([
                'guest_name'     => 'required|string',
                'reservation_id' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        // 1. Update status kamar → occupied
        $room->update(['status' => 'occupied']);
        $assignedAt = now()->toISOString();

        // 2. Integrasi Central Infrastructure (SSO → SOAP → RabbitMQ)
        $integrationResult = $this->triggerCentralInfrastructure(
            activityName: 'RoomAssigned',
            logData: [
                'room_id'        => $room->id,
                'room_number'    => $room->room_number,
                'type'           => $room->type,
                'floor'          => $room->floor,
                'price_per_night'=> $room->price_per_night,
                'guest_name'     => $validated['guest_name'],
                'reservation_id' => $validated['reservation_id'],
                'assigned_at'    => $assignedAt,
                'action'         => 'room_assigned',
                'justification'  => 'Assign kamar ke tamu adalah transaksi kritis: mengubah status ketersediaan kamar (state-changing) dan melibatkan nilai finansial (harga per malam). Wajib diaudit untuk akuntabilitas operasional hotel.',
            ],
            mqPublisher: fn(string $token) => $this->mq->publishRoomAssigned(
                $token,
                $room->toArray(),
                $validated['guest_name'],
                $validated['reservation_id']
            )
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Room successfully assigned to guest',
            'data'    => [
                'room'           => $room->fresh(),
                'guest_name'     => $validated['guest_name'],
                'reservation_id' => $validated['reservation_id'],
                'assigned_at'    => $assignedAt,
            ],
            'meta'    => [
                'service_name'    => 'Room-Catalog-Service',
                'api_version'     => 'v1',
                'iae_integration' => $integrationResult,
            ],
        ]);
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
}
