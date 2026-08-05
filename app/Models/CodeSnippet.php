<?php

namespace App\Models;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Support\RequestCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Operator-supplied markup injected into the public site.
 *
 * `code` is emitted unescaped by `resources/views/components/code-snippets.blade.php`.
 * That is the feature: the trust boundary is panel access, not this column.
 */
class CodeSnippet extends Model
{
    /** @use HasFactory<\Database\Factories\CodeSnippetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'position',
        'priority',
        'code',
        'description',
        'is_active',
        'skip_for_admins',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SnippetType::class,
            'position' => SnippetPosition::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
            'skip_for_admins' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saved(fn () => RequestCache::flush('code_snippets'));
        static::deleted(fn () => RequestCache::flush('code_snippets'));
    }
}
