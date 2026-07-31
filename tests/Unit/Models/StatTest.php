<?php

namespace Tests\Unit\Models;

use App\Enums\StatFormat;
use App\Models\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_format_to_the_enum(): void
    {
        $stat = Stat::create([
            'label' => ['en' => 'Hectares'],
            'value' => 45,
            'format' => StatFormat::Plain,
        ]);

        $this->assertSame(StatFormat::Plain, $stat->fresh()->format);
    }

    public function test_it_defaults_to_thousands_format(): void
    {
        $stat = Stat::create(['label' => ['en' => 'Workers'], 'value' => 1200]);

        $this->assertSame(StatFormat::Thousands, $stat->fresh()->format);
    }

    public function test_is_plain_reflects_the_format(): void
    {
        $plain = Stat::create(['label' => ['en' => 'A'], 'value' => 45, 'format' => StatFormat::Plain]);
        $thousands = Stat::create(['label' => ['en' => 'B'], 'value' => 1200, 'format' => StatFormat::Thousands]);

        $this->assertTrue($plain->isPlain());
        $this->assertFalse($thousands->isPlain());
    }

    public function test_it_stores_a_suffix(): void
    {
        $stat = Stat::create(['label' => ['en' => 'Uptime'], 'value' => 99, 'suffix' => '%']);

        $this->assertSame('%', $stat->fresh()->suffix);
    }

    public function test_ordered_scope_applies(): void
    {
        Stat::create(['label' => ['en' => 'Second'], 'value' => 2, 'sort' => 2]);
        Stat::create(['label' => ['en' => 'First'], 'value' => 1, 'sort' => 1]);

        $labels = Stat::query()->ordered()->get()->map(fn ($s) => $s->t('label', 'en'));

        $this->assertSame(['First', 'Second'], $labels->all());
    }

    public function test_enum_labels_are_human_readable(): void
    {
        $this->assertSame('Plain (45)', StatFormat::Plain->label());
        $this->assertSame('Thousands separated (1,200)', StatFormat::Thousands->label());
    }

    public function test_value_is_cast_to_float(): void
    {
        $stat = Stat::create(['label' => ['en' => 'Count'], 'value' => 45]);

        $fresh = $stat->fresh();
        $this->assertIsFloat($fresh->value);
        $this->assertSame(45.0, $fresh->value);
        $this->assertSame('45', (string) $fresh->value);
    }

    public function test_value_cast_preserves_decimals(): void
    {
        $stat = Stat::create(['label' => ['en' => 'Count'], 'value' => 45.5]);

        $fresh = $stat->fresh();
        $this->assertIsFloat($fresh->value);
        $this->assertSame(45.5, $fresh->value);
        $this->assertSame('45.5', (string) $fresh->value);
    }

    public function test_stat_format_options_returns_correct_map(): void
    {
        $options = StatFormat::options();

        $this->assertSame([
            'plain' => 'Plain (45)',
            'thousands' => 'Thousands separated (1,200)',
        ], $options);
    }
}
