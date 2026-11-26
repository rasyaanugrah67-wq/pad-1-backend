<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotificationLog extends Model
{
    use HasFactory;

    public const STATE_PROCESSING = 'processing';
    public const STATE_QUEUED = 'queued';
    public const STATE_SKIPPED = 'skipped';
    public const STATE_FAILED = 'failed';

    protected $table = 'whatsapp_notification_logs';

    protected $fillable = [
        'trigger_type',
        'reference_type',
        'reference_id',
        'id_warga',
        'dedup_key',
        'delivery_state',
        'is_test_mode',
        'provider_process',
        'provider_detail',
        'error_reason',
        'attempts',
        'sent_at',
    ];

    protected $casts = [
        'is_test_mode' => 'boolean',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function warga(): BelongsTo
    {
        return $this->belongsTo(Warga::class, 'id_warga', 'id_warga');
    }
}
