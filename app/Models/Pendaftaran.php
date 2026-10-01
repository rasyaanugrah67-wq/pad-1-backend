<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pendaftaran extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran';
    protected $primaryKey = 'id_pendaftaran';

    protected $fillable = [
        'id_warga',
        'id_lomba',
        'tanggal_daftar',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_daftar' => 'datetime',
    ];

    public function warga(): BelongsTo
    {
        return $this->belongsTo(Warga::class, 'id_warga', 'id_warga');
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class, 'id_lomba', 'id_lomba');
    }
}

