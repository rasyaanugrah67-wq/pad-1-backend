<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pertandingan extends Model
{
    use HasFactory;

    protected $table = 'pertandingan';
    protected $primaryKey = 'id_pertandingan';

    protected $fillable = [
        'id_lomba',
        'id_jadwal',
        'peserta_1',
        'peserta_2',
        'babak',
        'waktu_mulai',
        'waktu_selesai',
        'status',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(
            Lomba::class,
            'id_lomba',
            'id_lomba'
        );

    }

    
    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(
            JadwalLomba::class,
            'id_jadwal',
            'id_jadwal'
        );
    }
    
}