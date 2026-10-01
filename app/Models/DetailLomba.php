<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailLomba extends Model
{
    use HasFactory;

    protected $table = 'detail_lomba';
    protected $primaryKey = 'id_detail';

    protected $fillable = [
        'id_lomba',
        'deskripsi',
        'alur',
        'syarat',
        'peraturan',
    ];

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class, 'id_lomba', 'id_lomba');
    }
}

