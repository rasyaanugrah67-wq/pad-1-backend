<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warga extends Model
{
    use HasFactory;

    protected $table = 'warga';

    protected $primaryKey = 'id_warga';

    protected $fillable = [
        'id_rt',
        'nik',
        'nama',
        'tempat_lahir',
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

    public function pendaftaran()
    {
        return $this->hasMany(Pendaftaran::class, 'id_warga', 'id_warga');
    }

    public function iuran()
    {
        return $this->hasMany(Iuran::class, 'id_warga', 'id_warga');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'id_warga', 'id_warga');
    }
}
