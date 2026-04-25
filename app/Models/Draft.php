<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Draft extends Model
{
    /** @use HasFactory<DraftFactory> */
    use HasFactory;
}
