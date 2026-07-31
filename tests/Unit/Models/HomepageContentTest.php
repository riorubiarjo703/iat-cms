<?php

namespace Tests\Unit\Models;

use App\Models\HomepageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_singleton_creates_the_row_on_a_fresh_database(): void
    {
        $this->assertSame(0, HomepageContent::query()->count());

        $content = HomepageContent::singleton();

        $this->assertSame(1, $content->id);
        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_singleton_is_idempotent(): void
    {
        $first = HomepageContent::singleton();
        $second = HomepageContent::singleton();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_it_declares_fourteen_translatable_fields(): void
    {
        $this->assertCount(14, HomepageContent::TRANSLATABLE);
        $this->assertSame(HomepageContent::TRANSLATABLE, (new HomepageContent)->translatableFields());
    }

    public function test_translatable_columns_round_trip_as_arrays(): void
    {
        $content = HomepageContent::singleton();
        $content->update(['hero_line' => ['en' => "The district that\nnever sleeps", 'id' => 'Kawasan']]);

        $this->assertSame('Kawasan', $content->fresh()->t('hero_line', 'id'));
    }

    public function test_it_falls_back_to_english_for_an_untranslated_field(): void
    {
        $content = HomepageContent::singleton();
        $content->update(['contact_heading' => ['en' => 'Take an address']]);

        $this->assertSame('Take an address', $content->fresh()->t('contact_heading', 'cn'));
    }

    public function test_plain_columns_are_not_cast_to_array(): void
    {
        $casts = (new HomepageContent)->getCasts();

        foreach (['hero_image', 'about_image', 'about_cta_url', 'contact_email', 'contact_phone', 'contact_address'] as $plain) {
            $this->assertArrayNotHasKey($plain, $casts, "{$plain} must not be cast");
        }

        foreach (HomepageContent::TRANSLATABLE as $translatable) {
            $this->assertSame('array', $casts[$translatable] ?? null, "{$translatable} must be cast to array");
        }
    }

    public function test_plain_columns_round_trip_as_strings(): void
    {
        $content = HomepageContent::singleton();
        $content->update(['contact_email' => 'hello@scbd.com']);

        $this->assertSame('hello@scbd.com', $content->fresh()->contact_email);
    }
}
