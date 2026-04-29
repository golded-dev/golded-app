<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AreaFactory;
use Golded\Ftn\Database\Models\Area as BaseArea;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $echoid
 * @property string|null $source_type
 * @property string|null $area_type
 * @property string|null $source_group_code
 * @property int $source_sort_order
 * @property int|null $message_count
 * @property int|null $unread_count
 * @property int|null $last_read_msgno
 */
class Area extends BaseArea
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'echoid',
        'source_type',
        'area_type',
        'source_group_code',
        'source_sort_order',
        'message_count',
        'unread_count',
        'last_read_msgno',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'message_count' => 'integer',
            'unread_count' => 'integer',
            'last_read_msgno' => 'integer',
        ];
    }
}
