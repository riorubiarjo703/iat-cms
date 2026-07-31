<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translatable columns hold {en, id, cn} maps. Column order mirrors the
     * order the sections appear on the homepage.
     */
    private const TRANSLATABLE = [
        'brand_sub',
        'hero_line',
        'hero_sub',
        'about_heading',
        'about_body',
        'about_cta_label',
        'district_heading',
        'district_body',
        'facilities_heading',
        'facilities_body',
        'news_heading',
        'news_cta_label',
        'contact_heading',
        'marquee_text',
    ];

    public function up(): void
    {
        Schema::create('homepage_contents', function (Blueprint $table) {
            $table->id();

            foreach (self::TRANSLATABLE as $column) {
                $table->json($column)->nullable();
            }

            $table->string('hero_image')->nullable();
            $table->string('about_image')->nullable();
            $table->string('about_cta_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_contents');
    }
};
