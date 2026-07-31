<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE blog_posts MODIFY status ENUM('draft', 'published', 'scheduled') DEFAULT 'published'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        DB::table('blog_posts')
            ->where('status', 'scheduled')
            ->update(['status' => 'draft']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE blog_posts MODIFY status ENUM('draft', 'published') DEFAULT 'published'");
        }
    }
};
