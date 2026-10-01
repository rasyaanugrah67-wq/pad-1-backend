<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Akun extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'akun';

    protected $primaryKey = 'id_akun';

    public $timestamps = false;

    protected $fillable = [
        'id_warga',
        'id_role',
        'username',
        'password',
        'status',
        'last_login',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'last_login' => 'datetime',
    ];

    public function warga()
    {
        return $this->belongsTo(Warga::class, 'id_warga', 'id_warga');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role', 'id_role');
    }

    public function isActive(): bool
    {
        return $this->status === 'Aktif';
    }

    public function hasRole(string $role): bool
    {
        return strtolower($this->role?->nama_role ?? '') === strtolower($role);
    }
}