<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlbumDokumentasi extends Model
{
    use HasFactory;

    protected $table = 'album_dokumentasi';
    protected $primaryKey = 'id_album';

    protected $fillable = [
        'id_periode',
        'id_lomba',
        'nama_album',
        'tanggal_album',
        'cover',
    ];

    protected $casts = ['tanggal_album' => 'date'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'id_periode', 'id_periode');
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class, 'id_lomba', 'id_lomba');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Dokumentasi::class, 'id_album', 'id_album');
    }
}
