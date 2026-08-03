<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a page become the site's front page, and gives the brand subtitle a
 * home outside HomepageContent.
 *
 * The subtitle sits beside the logo in the header, which renders on every
 * page — so it is a Site Settings fact, not homepage copy, the same reasoning
 * that moved the contact details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('is_homepage')->default(false);
        });

        // A partial unique index: many pages may be false, only one true.
        // Enforced by the database rather than by application code, because a
        // second homepage would make which page "/" serves non-deterministic.
        DB::statement('CREATE UNIQUE INDEX pages_single_homepage ON pages (is_homepage) WHERE is_homepage');

        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('brand_subtitle')->nullable();
        });

        if (Schema::hasTable('homepage_contents')) {
            $source = DB::table('homepage_contents')->value('brand_sub');

            if ($source !== null) {
                DB::table('site_settings')->update(['brand_subtitle' => $source]);
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pages_single_homepage');

        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('is_homepage'));
        Schema::table('site_settings', fn (Blueprint $table) => $table->dropColumn('brand_subtitle'));
    }
};
