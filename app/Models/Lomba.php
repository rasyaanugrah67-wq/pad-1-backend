<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lomba extends Model
{
    use HasFactory;

    protected $table = 'lomba';
    protected $primaryKey = 'id_lomba';

    protected $fillable = [
        'id_periode',
        'id_kategori',
        'nama_lomba',
        'deskripsi',
        'gambar',
        'status',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'id_periode', 'id_periode');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriLomba::class, 'id_kategori', 'id_kategori');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(DetailLomba::class, 'id_lomba', 'id_lomba');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalLomba::class, 'id_lomba', 'id_lomba');
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class, 'id_lomba', 'id_lomba');
    }

    public function dokumentasi(): HasMany
    {
        return $this->hasMany(Dokumentasi::class, 'id_lomba', 'id_lomba');
    }
}

