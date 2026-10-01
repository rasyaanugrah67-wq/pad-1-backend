<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran';
    protected $primaryKey = 'id_pembayaran';

    protected $fillable = [
        'id_iuran',
        'tanggal_bayar',
        'nominal',
        'metode',
        'bukti_bayar',
        'status_verifikasi',
        'diverifikasi_oleh',
        'tanggal_verifikasi',
    ];

    protected $casts = [
        'tanggal_bayar' => 'datetime',
        'tanggal_verifikasi' => 'datetime',
        'nominal' => 'decimal:2',
    ];

    public function iuran(): BelongsTo
    {
        return $this->belongsTo(Iuran::class, 'id_iuran', 'id_iuran');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'diverifikasi_oleh', 'id_akun');
    }
}

