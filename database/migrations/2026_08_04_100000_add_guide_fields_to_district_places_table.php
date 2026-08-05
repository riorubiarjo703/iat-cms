<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The District Facilities page presents each place as a full row rather than
 * the homepage's narrow strip, which leaves room for a paragraph, a set of tag
 * chips and one headline figure. `caption` stays as it is — it is the short
 * label the homepage strip shows, not this paragraph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('district_places', function (Blueprint $table) {
            $table->json('body')->nullable()->after('caption');
            // One comma-separated line per locale. A nested array would need a
            // repeater inside every locale tab to edit.
            $table->json('tags')->nullable()->after('body');
            $table->json('stat_label')->nullable()->after('tags');
            // A figure, not prose: "18K+" reads the same in every language.
            $table->string('stat_value')->nullable()->after('stat_label');
        });
    }

    public function down(): void
    {
        Schema::table('district_places', function (Blueprint $table) {
            $table->dropColumn(['body', 'tags', 'stat_label', 'stat_value']);
        });
    }
};
