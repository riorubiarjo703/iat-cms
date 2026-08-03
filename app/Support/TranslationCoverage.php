<?php

namespace App\Support;

use App\Concerns\HasTranslatableFields;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Per-locale translation coverage, discovered rather than declared: every model
 * composing HasTranslatableFields is walked and its TRANSLATABLE constant read,
 * so the panel keeps working as models come and go.
 *
 * NOTE: after the Graper slice, page copy moves into page_translations and this
 * calculation will need extending to cover it. Recorded here so it is a known
 * limit rather than a later surprise.
 */
class TranslationCoverage
{
    /**
     * Percentage filled per locale.
     *
     * A locale with no translatable content at all maps to null, not 0 — zero
     * would read as "everything is untranslated" when the truth is "there is
     * nothing to translate".
     *
     * @return array<string, array{label: string, percent: int|null, filled: int, total: int}>
     */
    public function perLocale(): array
    {
        $models = $this->translatableModels();
        $coverage = [];

        foreach (SiteSetting::LOCALES as $locale => $label) {
            $filled = 0;
            $total = 0;

            foreach ($models as $model) {
                [$modelFilled, $modelTotal] = $this->countForModel($model, $locale);
                $filled += $modelFilled;
                $total += $modelTotal;
            }

            $coverage[$locale] = [
                'label' => $label,
                'percent' => $total > 0 ? (int) round($filled / $total * 100) : null,
                'filled' => $filled,
                'total' => $total,
            ];
        }

        return $coverage;
    }

    public function hasTranslatableContent(): bool
    {
        return $this->translatableModels() !== [];
    }

    /**
     * Models composing the concern. Discovered from the filesystem so nothing
     * hardcodes a model list.
     *
     * @return array<int, class-string<Model>>
     */
    public function translatableModels(): array
    {
        $models = [];

        foreach (File::files(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            if (! in_array(HasTranslatableFields::class, $this->traitsOf($class), true)) {
                continue;
            }

            // A model can compose the concern and still declare no fields.
            if (! defined($class.'::TRANSLATABLE') || $class::TRANSLATABLE === []) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    /** @return array<int, string> */
    private function traitsOf(string $class): array
    {
        $traits = [];

        foreach (array_merge([$class], class_parents($class) ?: []) as $level) {
            $traits = array_merge($traits, class_uses($level) ?: []);
        }

        return array_values($traits);
    }

    /**
     * @param  class-string<Model>  $model
     * @return array{0: int, 1: int} filled, total
     */
    private function countForModel(string $model, string $locale): array
    {
        try {
            $instance = new $model;

            if (! Schema::connection($instance->getConnectionName())->hasTable($instance->getTable())) {
                return [0, 0];
            }

            $fields = $model::TRANSLATABLE;
            $filled = 0;
            $total = 0;

            foreach ($model::query()->get() as $record) {
                foreach ($fields as $field) {
                    $total++;

                    $value = $record->getAttributes()[$field] ?? null;
                    $decoded = is_string($value) ? json_decode($value, true) : $value;

                    if (is_array($decoded) && filled($decoded[$locale] ?? null)) {
                        $filled++;
                    }
                }
            }

            return [$filled, $total];
        } catch (Throwable) {
            return [0, 0];
        }
    }
}
