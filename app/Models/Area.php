<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'echoid', 'group_id', 'source_type',
        'sort_order', 'message_count', 'unread_count', 'last_read_msgno',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
