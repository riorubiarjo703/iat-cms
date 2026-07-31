<?php

namespace App\Models;

use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use App\Enums\StatFormat;
use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'format' => StatFormat::class,
        'value' => 'float',
        'sort' => 'integer',
    ];

    /**
     * Maps to the reference's `data-plain` attribute on the count-up element.
     */
    public function isPlain(): bool
    {
        return $this->format === StatFormat::Plain;
    }
}
