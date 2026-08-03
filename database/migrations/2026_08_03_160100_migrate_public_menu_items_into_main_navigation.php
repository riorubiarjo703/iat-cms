<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the existing public menu into a "Main Navigation" menu assigned to the
 * header location.
 *
 * Copies rather than transforms: public_menu_items is left intact so the old
 * and new sources can be compared before the old table is dropped. Translations
 * carry across verbatim — the JSON column shape is identical on both sides —
 * and the CTA keeps its flag, since a call-to-action is an ordinary item with
 * is_cta set rather than a separate concept.
 *
 * Idempotent: re-running finds the menu already there and does nothing, so a
 * partially applied migration cannot double the items.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_menu_items') || ! Schema::hasTable('menus')) {
            return;
        }

        if (DB::table('menus')->where('slug', 'main-navigation')->exists()) {
            return;
        }

        $rows = DB::table('public_menu_items')->orderBy('sort')->get();

        // Nothing to migrate means nothing to create. Without this a fresh
        // install — and every test database — would start with an empty menu
        // already occupying the header location.
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        $menuId = DB::table('menus')->insertGetId([
            'name' => 'Main Navigation',
            'slug' => 'main-navigation',
            'location' => 'header',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($rows as $row) {
            DB::table('menu_items')->insert([
                'menu_id' => $menuId,
                'parent_id' => null,
                'type' => 'custom',
                'label' => $row->label,
                'url' => $row->url,
                'linkable_type' => null,
                'linkable_id' => null,
                'target' => $row->target ?: '_self',
                'is_cta' => $row->is_cta,
                'is_active' => $row->is_active,
                'sort' => $row->sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', 'main-navigation')->value('id');

        if ($menuId !== null) {
            DB::table('menu_items')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }
};
