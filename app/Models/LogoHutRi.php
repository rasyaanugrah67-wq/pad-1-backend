<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogoHutRi extends Model
{
    use HasFactory;

    protected $table = 'logo_hut_ri';

    protected $primaryKey = 'id_logo';

    protected $fillable = [
        'tahun',
        'nama_logo',
        'gambar',
        'status',
    ];

    protected $casts = [
        'tahun' => 'integer',
    ];
}
