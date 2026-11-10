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
        'cloudinary_public_id',
        'cloudinary_format',
        'status_verifikasi',
        'diverifikasi_oleh',
        'tanggal_verifikasi',
    ];

    protected $casts = [
        'tanggal_bayar' => 'datetime',
        'tanggal_verifikasi' => 'datetime',
        'nominal' => 'decimal:2',
    ];

    protected $appends = [
        'bukti_bayar_url',
    ];

    public function getBuktiBayarUrlAttribute(): ?string
    {
        if (!$this->bukti_bayar) {
            return null;
        }

        // Bukti Cloudinary harus diakses melalui endpoint terproteksi.
        if ($this->cloudinary_public_id) {
            return url('/api/pembayaran/' . $this->id_pembayaran . '/bukti');
        }

        // Kompatibilitas dengan data lama.
        if (filter_var($this->bukti_bayar, FILTER_VALIDATE_URL)) {
            return $this->bukti_bayar;
        }

        return asset('storage/' . $this->bukti_bayar);
    }

    public function iuran(): BelongsTo
    {
        return $this->belongsTo(Iuran::class, 'id_iuran', 'id_iuran');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'diverifikasi_oleh', 'id_akun');
    }
}