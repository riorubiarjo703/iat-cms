<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the tables the hand-built homepage used.
 *
 * homepage_contents held the homepage copy; that copy now lives in the
 * homepage page's block payload, and the contact details and brand subtitle
 * moved to site_settings. public_menu_items held the header navigation; it was
 * copied into a Main Navigation menu and the site has rendered from menu_items
 * since, so the old table has been unread for several slices.
 *
 * There is no down(): both tables were the source for data that has since been
 * transformed, not merely moved, so recreating empty tables would not restore
 * anything. Recovery is a git revert plus a re-seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('homepage_contents');
        Schema::dropIfExists('public_menu_items');
    }
};
