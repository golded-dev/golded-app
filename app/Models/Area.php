<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $echoid
 * @property string|null $group_id
 * @property string|null $source_type
 * @property string|null $area_type
 * @property int $sort_order
 * @property int|null $message_count
 * @property int|null $unread_count
 * @property int|null $last_read_msgno
 */
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'echoid', 'group_id', 'source_type', 'area_type',
        'sort_order', 'message_count', 'unread_count', 'last_read_msgno',
    ];

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
