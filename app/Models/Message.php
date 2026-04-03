<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'area_id', 'msgno', 'external_id',
        'subject', 'from_name', 'from_address', 'to_name', 'to_address',
        'body_text', 'reply_to_msgno', 'reply_to_external_id',
        'reply1st_msgno', 'replynext_msgno', 'thread_key',
        'attributes_raw', 'posted_at', 'arrived_at',
        'is_read', 'is_marked', 'is_bookmarked', 'raw_metadata_json',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'arrived_at' => 'datetime',
        'is_read' => 'boolean',
        'is_marked' => 'boolean',
        'is_bookmarked' => 'boolean',
        'raw_metadata_json' => 'array',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
