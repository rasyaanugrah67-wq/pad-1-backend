<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rt extends Model
{
    use HasFactory;

    protected $table = 'rt';

    protected $primaryKey = 'id_rt';

    public $timestamps = false;

    protected $fillable = [
        'id_rw',
        'nomor_rt',
        'nama_rt',
    ];

    public function rw()
    {
        return $this->belongsTo(Rw::class, 'id_rw', 'id_rw');
    }

    public function warga()
    {
        return $this->hasMany(Warga::class, 'id_rt', 'id_rt');
    }
}