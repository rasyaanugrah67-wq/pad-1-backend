<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $table = 'role';

    protected $primaryKey = 'id_role';

    public $timestamps = false;

    protected $fillable = [
        'nama_role',
        'deskripsi',
    ];

    public function akun()
    {
        return $this->hasMany(Akun::class, 'id_role', 'id_role');
    }
}