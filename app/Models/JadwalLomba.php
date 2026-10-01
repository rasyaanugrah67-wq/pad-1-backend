<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalLomba extends Model
{
    use HasFactory;

    protected $table = 'jadwal_lomba';
    protected $primaryKey = 'id_jadwal';

    protected $fillable = [
        'id_lomba',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'lokasi',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class, 'id_lomba', 'id_lomba');
    }
}

