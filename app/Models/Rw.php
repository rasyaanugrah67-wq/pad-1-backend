<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rw extends Model
{
    use HasFactory;

    protected $table = 'rw';

    protected $primaryKey = 'id_rw';

    public $timestamps = false;

    protected $fillable = [
        'nomor_rw',
        'nama_rw',
    ];

    public function rt()
    {
        return $this->hasMany(Rt::class, 'id_rw', 'id_rw');
    }
}