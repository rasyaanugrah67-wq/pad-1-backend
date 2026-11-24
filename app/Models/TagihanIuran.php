<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagihanIuran extends Model
{
    use HasFactory;

    protected $table = 'tagihan_iuran';
    protected $primaryKey = 'id_tagihan';

    protected $fillable = [
        'id_periode',
        'nama_tagihan',
        'nominal',
        'jatuh_tempo',
        'keterangan',
        'target',
        'is_aktif',
        'created_by',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'jatuh_tempo' => 'date',
        'is_aktif' => 'boolean',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'id_periode', 'id_periode');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'created_by', 'id_akun');
    }

    public function iuran(): HasMany
    {
        return $this->hasMany(Iuran::class, 'id_tagihan', 'id_tagihan');
    }
}
