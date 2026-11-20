<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    use HasFactory;

    protected $table = 'periode';
    protected $primaryKey = 'id_periode';

    protected $fillable = [
        'tahun',
        'nama_kegiatan',
        'logo',
        'deskripsi',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function lomba(): HasMany
    {
        return $this->hasMany(Lomba::class, 'id_periode', 'id_periode');
    }

    public function kepanitiaan(): HasMany
    {
        return $this->hasMany(Kepanitiaan::class, 'id_periode', 'id_periode');
    }

    public function iuran(): HasMany
    {
        return $this->hasMany(Iuran::class, 'id_periode', 'id_periode');
    }

    public function albumDokumentasi(): HasMany
    {
        return $this->hasMany(AlbumDokumentasi::class, 'id_periode', 'id_periode');
    }
}

