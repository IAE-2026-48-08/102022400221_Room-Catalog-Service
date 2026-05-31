<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'type',
        'floor',
        'capacity',
        'price_per_night',
        'status',
        'description',
        'facilities',
    ];

    protected $casts = [
    'facilities'      => 'array',
    'price_per_night' => 'float',
    'floor'           => 'integer',
    'capacity'        => 'integer',
];
}
