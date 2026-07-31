<?php

namespace Tests\Unit\Concerns;

use App\Concerns\HasTranslatableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TranslatableStub extends Model
{
    use HasTranslatableFields;

    protected $table = 'translatable_stubs';

    protected $guarded = [];

    protected array $translatable = ['title', 'body'];

    protected $casts = ['is_active' => 'boolean'];
}

class HasTranslatableFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('translatable_stubs', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_it_casts_translatable_columns_to_array(): void
    {
        $stub = TranslatableStub::create([
            'title' => ['en' => 'Hello', 'id' => 'Halo', 'cn' => '你好'],
        ]);

        $this->assertSame(['en' => 'Hello', 'id' => 'Halo', 'cn' => '你好'], $stub->fresh()->title);
    }

    public function test_it_preserves_casts_declared_on_the_model(): void
    {
        $stub = new TranslatableStub(['is_active' => '1']);

        $this->assertTrue($stub->is_active);
        $this->assertSame('array', $stub->getCasts()['title']);
    }

    public function test_it_returns_the_value_for_the_requested_locale(): void
    {
        $stub = new TranslatableStub(['title' => ['en' => 'Hello', 'id' => 'Halo']]);

        $this->assertSame('Halo', $stub->t('title', 'id'));
    }

    public function test_it_defaults_to_the_application_locale(): void
    {
        app()->setLocale('id');
        $stub = new TranslatableStub(['title' => ['en' => 'Hello', 'id' => 'Halo']]);

        $this->assertSame('Halo', $stub->t('title'));
    }

    public function test_it_falls_back_to_english_when_the_locale_is_missing(): void
    {
        $stub = new TranslatableStub(['title' => ['en' => 'Hello']]);

        $this->assertSame('Hello', $stub->t('title', 'cn'));
    }

    public function test_it_falls_back_to_english_when_the_locale_value_is_blank(): void
    {
        $stub = new TranslatableStub(['title' => ['en' => 'Hello', 'cn' => '   ']]);

        $this->assertSame('Hello', $stub->t('title', 'cn'));
    }

    public function test_it_returns_null_when_no_value_exists_at_all(): void
    {
        $stub = new TranslatableStub(['title' => null]);

        $this->assertNull($stub->t('title', 'en'));
    }

    public function test_it_returns_the_full_locale_map(): void
    {
        $stub = new TranslatableStub(['body' => ['en' => 'A', 'id' => 'B']]);

        $this->assertSame(['en' => 'A', 'id' => 'B'], $stub->translations('body'));
    }

    public function test_it_returns_an_empty_map_for_a_null_column(): void
    {
        $stub = new TranslatableStub(['body' => null]);

        $this->assertSame([], $stub->translations('body'));
    }

    public function test_it_exposes_the_declared_translatable_fields(): void
    {
        $this->assertSame(['title', 'body'], (new TranslatableStub)->translatableFields());
    }
}
