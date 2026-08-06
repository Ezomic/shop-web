<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
