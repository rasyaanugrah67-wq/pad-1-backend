<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimLomba extends Model
{
    protected $table = 'tim_lomba';

    protected $primaryKey = 'id_tim';

    protected $fillable = [
        'id_lomba',
        'nama_tim',
    ];

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(
            Lomba::class,
            'id_lomba',
            'id_lomba'
        );
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(
            Pendaftaran::class,
            'id_tim',
            'id_tim'
        );
    }
}
