<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the tables owned by cybertroniankelvin/graper and
 * vaslv/filament-topbar-menu, both of which are being replaced by our own
 * implementations.
 *
 * Both tables were verified empty before this was written (graper_pages: 0
 * rows, filament_topbar_menu_items: 0 rows), so no content is lost and no
 * migration of existing data is needed.
 *
 * There is no down(): the packages are gone, so their schema definitions are
 * gone too, and recreating these tables would mean hand-copying vendor schema
 * that nothing reads. Restoring them means restoring the packages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('graper_pages');
        Schema::dropIfExists('filament_topbar_menu_items');

        // Their migration files are deleted alongside this one; leaving the
        // ledger rows behind would make migrate:status report entries whose
        // files cannot be found.
        DB::table('migrations')
            ->whereIn('migration', [
                '2026_07_30_062928_create_graper_pages_table',
                'create_filament_topbar_menu_items_table',
            ])
            ->delete();
    }
};
