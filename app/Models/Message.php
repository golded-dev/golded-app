<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use Golded\Ftn\Database\Models\Message as BaseMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $area_id
 * @property int|null $msgno
 * @property string $source_type
 * @property string $source_uid
 * @property string|null $source_locator
 * @property int|null $source_offset
 * @property string|null $external_id
 * @property string|null $subject
 * @property string|null $from_name
 * @property string|null $from_address
 * @property string|null $to_name
 * @property string|null $to_address
 * @property string $body_text
 * @property string|null $body_raw
 * @property string|null $body_encoding
 * @property int|null $reply_to_msgno
 * @property string|null $reply_to_external_id
 * @property int|null $reply1st_msgno
 * @property int|null $replynext_msgno
 * @property string|null $thread_key
 * @property int $attributes_raw
 * @property Carbon|null $posted_at
 * @property Carbon|null $arrived_at
 * @property array<string, mixed>|null $control_lines_json
 * @property array<string, mixed>|null $provenance_json
 * @property bool $is_read
 * @property bool $is_marked
 * @property bool $is_bookmarked
 */
class Message extends BaseMessage
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'area_id',
        'msgno',
        'source_type',
        'source_uid',
        'source_locator',
        'source_offset',
        'external_id',
        'subject',
        'from_name',
        'from_address',
        'to_name',
        'to_address',
        'body_text',
        'body_raw',
        'body_encoding',
        'reply_to_msgno',
        'reply_to_external_id',
        'reply1st_msgno',
        'replynext_msgno',
        'attributes_raw',
        'posted_at',
        'arrived_at',
        'control_lines_json',
        'provenance_json',
        'is_read',
        'is_marked',
        'is_bookmarked',
        'thread_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_read' => 'boolean',
            'is_marked' => 'boolean',
            'is_bookmarked' => 'boolean',
        ];
    }
}
