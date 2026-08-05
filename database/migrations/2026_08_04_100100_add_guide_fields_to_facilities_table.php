<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companion to the district_places change: the District Facilities page labels
 * each facility with a kicker ("24/7 Operations") and closes it with one
 * headline figure. The homepage's card stack reads neither, so it is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->json('eyebrow')->nullable()->after('title');
            $table->json('stat_label')->nullable()->after('body');
            $table->string('stat_value')->nullable()->after('stat_label');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['eyebrow', 'stat_label', 'stat_value']);
        });
    }
};
