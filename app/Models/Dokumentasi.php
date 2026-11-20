<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumentasi extends Model
{
    use HasFactory;

    protected $table = 'dokumentasi';
    protected $primaryKey = 'id_dokumentasi';

    protected $fillable = [
        'id_album',
        'id_lomba',
        'id_panitia',
        'jenis_file',
        'file',
        'keterangan',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(AlbumDokumentasi::class, 'id_album', 'id_album');
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class, 'id_lomba', 'id_lomba');
    }

    public function panitia(): BelongsTo
    {
        return $this->belongsTo(Kepanitiaan::class, 'id_panitia', 'id_panitia');
    }
}

