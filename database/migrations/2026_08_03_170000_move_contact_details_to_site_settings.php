<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contact details belong to the organisation, not to the homepage.
 *
 * They lived on homepage_contents, which was fine while the homepage was the
 * only page. A footer renders on every page, so reading the address out of the
 * homepage's content record would make the footer depend on homepage copy.
 *
 * Copies rather than moves: the homepage_contents columns stay until the new
 * source is verified in place, so both can be compared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('contact_address')->nullable();
        });

        if (! Schema::hasTable('homepage_contents')) {
            return;
        }

        $source = DB::table('homepage_contents')->first();

        if ($source === null) {
            return;
        }

        DB::table('site_settings')->update([
            'contact_email' => $source->contact_email ?? null,
            'contact_phone' => $source->contact_phone ?? null,
            'contact_address' => $source->contact_address ?? null,
        ]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone', 'contact_address']);
        });
    }
};
