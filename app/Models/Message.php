<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $area_id
 * @property int $msgno
 * @property string|null $external_id
 * @property string|null $subject
 * @property string|null $from_name
 * @property string|null $from_address
 * @property string|null $to_name
 * @property string|null $to_address
 * @property string $body_text
 * @property int|null $reply_to_msgno
 * @property string|null $reply_to_external_id
 * @property int|null $reply1st_msgno
 * @property int|null $replynext_msgno
 * @property string|null $thread_key
 * @property string|null $attributes_raw
 * @property Carbon|null $posted_at
 * @property Carbon|null $arrived_at
 * @property bool $is_read
 * @property bool $is_marked
 * @property bool $is_bookmarked
 * @property array<mixed>|null $raw_metadata_json
 */
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

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'is_read' => 'boolean',
            'is_marked' => 'boolean',
            'is_bookmarked' => 'boolean',
            'raw_metadata_json' => 'array',
        ];
    }
}
