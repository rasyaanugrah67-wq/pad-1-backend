<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{
    use HasFactory;

    protected $table = 'pengumuman';
    protected $primaryKey = 'id_pengumuman';

    protected $fillable = [
        'id_periode', 'id_akun', 'judul', 'isi', 'tanggal_publikasi',
        'status', 'target_penerima', 'id_rt', 'id_rw',
    ];

    protected $casts = ['tanggal_publikasi' => 'datetime'];

    public function akun() { return $this->belongsTo(Akun::class, 'id_akun', 'id_akun'); }
    public function periode() { return $this->belongsTo(Periode::class, 'id_periode', 'id_periode'); }
    public function rt() { return $this->belongsTo(Rt::class, 'id_rt', 'id_rt'); }
    public function rw() { return $this->belongsTo(Rw::class, 'id_rw', 'id_rw'); }
}
