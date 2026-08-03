<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages come in two shapes, chosen per page:
 *
 *   simple  — rich text in `content`
 *   builder — a tree of typed blocks in `builder_payload`
 *
 * Both columns exist on every row regardless of type. Switching a page from
 * simple to builder must not destroy the text it had, and switching back must
 * not destroy the blocks; keeping both lets the choice be reversible.
 *
 * Translatable columns are JSON `{en,id,cn}` maps, the same shape every other
 * translatable model here uses, so translation coverage discovers them without
 * extra wiring. `builder_payload` is NOT per-locale — the block structure is
 * shared and only its text leaves carry locale maps, so the three languages
 * cannot drift into different layouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();

            $table->string('type')->default('simple');
            $table->json('content')->nullable();
            $table->json('builder_payload')->nullable();

            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();

            $table->timestamps();

            // The public route looks pages up by slug and status together.
            $table->index(['status', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
