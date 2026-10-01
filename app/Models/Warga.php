<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warga extends Model
{
    use HasFactory;

    protected $table = 'warga';

    protected $primaryKey = 'id_warga';

    public $timestamps = false;

    protected $fillable = [
        'id_rt',
        'nik',
        'nama',
        'no_hp',
        'alamat',
        'jenis_kelamin',
        'tanggal_lahir',
        'status',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    public function rt()
    {
        return $this->belongsTo(Rt::class, 'id_rt', 'id_rt');
    }

    public function akun()
    {
        return $this->hasOne(Akun::class, 'id_warga', 'id_warga');
    }
}