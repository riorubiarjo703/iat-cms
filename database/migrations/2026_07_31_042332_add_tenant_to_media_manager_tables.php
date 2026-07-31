<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_folders', function (Blueprint $table) {
            $table->string('tenant_id', 64)->nullable()->after('id');
            $table->index(['tenant_id', 'parent_id']);
        });

        Schema::table('media_files', function (Blueprint $table) {
            $table->string('tenant_id', 64)->nullable()->after('id');
            $table->index(['tenant_id', 'folder_id']);
        });

        Schema::table('media_tags', function (Blueprint $table) {
            $table->dropUnique('media_tags_slug_unique');
            $table->string('tenant_id', 64)->nullable()->after('id');
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('media_tags', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropColumn('tenant_id');
            $table->unique('slug');
        });

        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'folder_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('media_folders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'parent_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
