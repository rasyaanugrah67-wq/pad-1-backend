<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaPendaftaran extends Model
{
    use HasFactory;

    protected $table = 'anggota_pendaftaran';
    protected $primaryKey = 'id_anggota';

    protected $fillable = [
        'id_pendaftaran',
        'nama',
        'no_hp',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}

