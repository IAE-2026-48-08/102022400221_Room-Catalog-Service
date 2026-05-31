<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    /**
     * @OA\Get(
     *     path="/api/v1/rooms",
     *     summary="Mengambil daftar seluruh kamar",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan status kamar",
     *         required=false,
     *         @OA\Schema(type="string", enum={"available","occupied","maintenance"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter berdasarkan tipe kamar",
     *         required=false,
     *         @OA\Schema(type="string", enum={"standard","deluxe","suite","presidential"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar kamar berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Room")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="service_name", type="string", example="Room-Catalog-Service"),
     *                 @OA\Property(property="api_version", type="string", example="v1"),
     *                 @OA\Property(property="total", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized - API Key tidak valid")
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
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/rooms/{id}",
     *     summary="Mengambil data spesifik kamar berdasarkan ID",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kamar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data kamar berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Room")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized")
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
            'meta'    => [
                'service_name' => 'Room-Catalog-Service',
                'api_version'  => 'v1',
            ],
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/rooms",
     *     summary="Menambah data kamar baru",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"room_number","type","floor","capacity","price_per_night"},
     *             @OA\Property(property="room_number", type="string", example="101"),
     *             @OA\Property(property="type", type="string", enum={"standard","deluxe","suite","presidential"}, example="deluxe"),
     *             @OA\Property(property="floor", type="integer", example=1),
     *             @OA\Property(property="capacity", type="integer", example=2),
     *             @OA\Property(property="price_per_night", type="number", example=500000),
     *             @OA\Property(property="description", type="string", example="Kamar deluxe dengan pemandangan kolam renang"),
     *             @OA\Property(property="facilities", type="array", @OA\Items(type="string"), example={"WiFi","AC","TV","Mini Bar"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Kamar berhasil ditambahkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Room created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Room")
     *         )
     *     ),
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

        $room = Room::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Room created successfully',
            'data'    => $room,
            'meta'    => [
                'service_name' => 'Room-Catalog-Service',
                'api_version'  => 'v1',
            ],
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/rooms/{id}/status",
     *     summary="Mengubah status kamar (tersedia/terisi/maintenance)",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"available","occupied","maintenance"}, example="occupied")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status kamar berhasil diperbarui"),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized")
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
            'meta'    => [
                'service_name' => 'Room-Catalog-Service',
                'api_version'  => 'v1',
            ],
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/rooms/{id}/assign",
     *     summary="Menetapkan kamar untuk tamu (assign)",
     *     tags={"Rooms"},
     *     security={{"X-IAE-KEY":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"guest_name","reservation_id"},
     *             @OA\Property(property="guest_name", type="string", example="Budi Santoso"),
     *             @OA\Property(property="reservation_id", type="string", example="RES-2024-001")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Kamar berhasil di-assign ke tamu"),
     *     @OA\Response(response=409, description="Kamar sudah terisi"),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized")
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

        $room->update(['status' => 'occupied']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Room successfully assigned to guest',
            'data'    => [
                'room'           => $room->fresh(),
                'guest_name'     => $validated['guest_name'],
                'reservation_id' => $validated['reservation_id'],
                'assigned_at'    => now()->toISOString(),
            ],
            'meta'    => [
                'service_name' => 'Room-Catalog-Service',
                'api_version'  => 'v1',
            ],
        ], 200);
    }
}