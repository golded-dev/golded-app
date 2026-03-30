<?php

namespace App\Models;

use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use HasFactory;

    protected $fillable = ['name', 'source_type'];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
