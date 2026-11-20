<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pemenang extends Model
{
    use HasFactory;

    protected $table = 'pemenang';
    protected $primaryKey = 'id_pemenang';

    protected $fillable = [
        'id_lomba', 'id_pendaftaran', 'id_tim', 'peringkat', 'nama_pemenang', 'keterangan',
    ];

    public function lomba(): BelongsTo { return $this->belongsTo(Lomba::class, 'id_lomba', 'id_lomba'); }
    public function pendaftaran(): BelongsTo { return $this->belongsTo(Pendaftaran::class, 'id_pendaftaran', 'id_pendaftaran'); }
    public function tim(): BelongsTo { return $this->belongsTo(TimLomba::class, 'id_tim', 'id_tim'); }
}
