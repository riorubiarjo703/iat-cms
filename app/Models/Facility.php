<?php

namespace App\Models;

use App\Concerns\Activatable;
use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use Activatable;
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['title', 'eyebrow', 'body', 'stat_label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];
}
