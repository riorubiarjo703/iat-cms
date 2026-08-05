<?php

namespace App\Models;

use App\Concerns\Activatable;
use App\Concerns\HasTranslatableFields;
use App\Concerns\Orderable;
use Illuminate\Database\Eloquent\Model;

class DistrictPlace extends Model
{
    use Activatable;
    use HasTranslatableFields;
    use Orderable;

    /** @var array<int, string> */
    public const TRANSLATABLE = ['title', 'caption', 'body', 'tags', 'stat_label'];

    protected $guarded = [];

    protected array $translatable = self::TRANSLATABLE;

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    /**
     * The tag chips, from the comma-separated line stored for the locale.
     *
     * Blanks are dropped rather than rendered as empty chips, so a trailing
     * comma — which is what an editor leaves behind after deleting the last
     * tag — costs nothing.
     *
     * @return array<int, string>
     */
    public function tagList(?string $locale = null): array
    {
        $line = $this->t('tags', $locale);

        if (blank($line)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $line)), 'filled'));
    }
}
