<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The contact section's Social column was hardcoded in the template while
 * site_settings.social held a different, partial set. Making the column render
 * from settings would silently drop Facebook, X and LinkedIn, so their real
 * URLs are captured here first.
 *
 * Existing values win: anything already configured in Site Settings was set
 * deliberately and must not be overwritten by a template default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $row = DB::table('site_settings')->first();

        if ($row === null) {
            return;
        }

        $existing = json_decode($row->social ?? '{}', true) ?: [];

        $fromTemplate = [
            'facebook' => 'https://web.facebook.com/SCBD.ID',
            'twitter' => 'https://twitter.com/scbd_id',
            'instagram' => 'https://www.instagram.com/scbd_official/',
            'linkedin' => 'https://www.linkedin.com/feed/',
        ];

        foreach ($fromTemplate as $network => $url) {
            if (blank($existing[$network] ?? null)) {
                $existing[$network] = $url;
            }
        }

        DB::table('site_settings')->where('id', $row->id)->update(['social' => json_encode($existing)]);
    }

    public function down(): void
    {
        // Nothing to reverse: the values are now the source of truth.
    }
};
