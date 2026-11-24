<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Iuran extends Model
{
    use HasFactory;

    protected $table = 'iuran';
    protected $primaryKey = 'id_iuran';

    protected $fillable = [
        'id_tagihan',
        'id_warga',
        'id_periode',
        'nama_iuran',
        'nominal',
        'jatuh_tempo',
        'keterangan',
        'status',
        'is_aktif',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'jatuh_tempo' => 'date',
        'is_aktif' => 'boolean',
    ];

    public function getNamaIuranAttribute($value): string
    {
        return $value
            ?: ($this->tagihan?->nama_tagihan
                ?? ($this->periode ? 'Iuran Periode ' . $this->periode->tahun : 'Iuran Warga'));
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(TagihanIuran::class, 'id_tagihan', 'id_tagihan');
    }

    public function warga(): BelongsTo
    {
        return $this->belongsTo(Warga::class, 'id_warga', 'id_warga');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'id_periode', 'id_periode');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'id_iuran', 'id_iuran');
    }
}

