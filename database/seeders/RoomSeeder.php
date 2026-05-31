<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'room_number'     => '101',
                'type'            => 'standard',
                'floor'           => 1,
                'capacity'        => 2,
                'price_per_night' => 350000,
                'status'          => 'available',
                'description'     => 'Kamar standard nyaman dengan fasilitas lengkap',
                'facilities'      => ['WiFi', 'AC', 'TV', 'Kamar Mandi Dalam'],
            ],
            [
                'room_number'     => '102',
                'type'            => 'standard',
                'floor'           => 1,
                'capacity'        => 2,
                'price_per_night' => 350000,
                'status'          => 'occupied',
                'description'     => 'Kamar standard dengan view taman',
                'facilities'      => ['WiFi', 'AC', 'TV', 'Kamar Mandi Dalam'],
            ],
            [
                'room_number'     => '201',
                'type'            => 'deluxe',
                'floor'           => 2,
                'capacity'        => 2,
                'price_per_night' => 550000,
                'status'          => 'available',
                'description'     => 'Kamar deluxe dengan pemandangan kolam renang',
                'facilities'      => ['WiFi', 'AC', 'TV', 'Mini Bar', 'Bathtub', 'Balkon'],
            ],
            [
                'room_number'     => '202',
                'type'            => 'deluxe',
                'floor'           => 2,
                'capacity'        => 3,
                'price_per_night' => 600000,
                'status'          => 'available',
                'description'     => 'Kamar deluxe family dengan extra bed',
                'facilities'      => ['WiFi', 'AC', 'TV', 'Mini Bar', 'Sofa Bed'],
            ],
            [
                'room_number'     => '301',
                'type'            => 'suite',
                'floor'           => 3,
                'capacity'        => 4,
                'price_per_night' => 1200000,
                'status'          => 'available',
                'description'     => 'Suite mewah dengan ruang tamu terpisah dan Jacuzzi',
                'facilities'      => ['WiFi', 'AC', 'Smart TV', 'Mini Bar', 'Jacuzzi', 'Ruang Tamu', 'Dapur Kecil'],
            ],
            [
                'room_number'     => '401',
                'type'            => 'presidential',
                'floor'           => 4,
                'capacity'        => 6,
                'price_per_night' => 3500000,
                'status'          => 'available',
                'description'     => 'Presidential Suite dengan fasilitas premium dan pemandangan panorama',
                'facilities'      => ['WiFi', 'AC', 'Smart TV', 'Bar Pribadi', 'Jacuzzi', 'Private Pool', 'Ruang Meeting', 'Butler Service'],
            ],
            [
                'room_number'     => '103',
                'type'            => 'standard',
                'floor'           => 1,
                'capacity'        => 1,
                'price_per_night' => 280000,
                'status'          => 'maintenance',
                'description'     => 'Kamar single standard, sedang dalam perbaikan',
                'facilities'      => ['WiFi', 'AC', 'TV'],
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }

        $this->command->info('✅ Room seeder completed: ' . count($rooms) . ' rooms created.');
    }
}
