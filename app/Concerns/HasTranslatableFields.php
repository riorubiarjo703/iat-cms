<?php

namespace App\Concerns;

trait HasTranslatableFields
{
    public const FALLBACK_LOCALE = 'en';

    /**
     * Merge `array` casts for every translatable column into whatever the model already declares.
     */
    public function getCasts(): array
    {
        return array_merge(
            parent::getCasts(),
            array_fill_keys($this->translatableFields(), 'array'),
        );
    }

    /**
     * @return array<int, string>
     */
    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    /**
     * @return array<string, string|null>
     */
    public function translations(string $key): array
    {
        $value = $this->getAttribute($key);

        return is_array($value) ? $value : [];
    }

    public function t(string $key, ?string $locale = null): ?string
    {
        $map = $this->translations($key);
        $locale ??= app()->getLocale();

        if (filled($map[$locale] ?? null)) {
            return $map[$locale];
        }

        return filled($map[static::FALLBACK_LOCALE] ?? null)
            ? $map[static::FALLBACK_LOCALE]
            : null;
    }
}
