<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriLomba extends Model
{
    use HasFactory;

    protected $table = 'kategori_lomba';
    protected $primaryKey = 'id_kategori';

    protected $fillable = [
        'nama_kategori',
        'deskripsi',
    ];

    public function lomba(): HasMany
    {
        return $this->hasMany(Lomba::class, 'id_kategori', 'id_kategori');
    }
}

