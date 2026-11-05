<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pendaftaran extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran';

    protected $primaryKey = 'id_pendaftaran';

    protected $fillable = [
        'id_warga',
        'id_lomba',
        'id_tim',
        'nama_tim',
        'tanggal_daftar',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_daftar' => 'datetime',
    ];

    public function warga(): BelongsTo
    {
        return $this->belongsTo(
            Warga::class,
            'id_warga',
            'id_warga'
        );
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(
            Lomba::class,
            'id_lomba',
            'id_lomba'
        );
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(
            AnggotaPendaftaran::class,
            'id_pendaftaran',
            'id_pendaftaran'
        );
    }

    public function tim(): BelongsTo
    {
        return $this->belongsTo(
            TimLomba::class,
            'id_tim',
            'id_tim'
        );
    }
}
