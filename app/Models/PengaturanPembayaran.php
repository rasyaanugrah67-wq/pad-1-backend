<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengaturanPembayaran extends Model
{
    use HasFactory;

    protected $table = 'pengaturan_pembayaran';
    protected $primaryKey = 'id_pengaturan';

    protected $fillable = [
        'nama_bank',
        'nomor_rekening',
        'atas_nama',
        'qris_image',
    ];
}
