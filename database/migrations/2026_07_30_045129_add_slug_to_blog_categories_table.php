<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('blog_categories', 'slug')) {
            return;
        }

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        DB::table('blog_categories')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (object $category): void {
                $baseSlug = Str::slug($category->name) ?: 'category-'.$category->id;
                $slug = $baseSlug;
                $counter = 2;

                while (
                    DB::table('blog_categories')
                        ->where('slug', $slug)
                        ->where('id', '!=', $category->id)
                        ->exists()
                ) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                DB::table('blog_categories')
                    ->where('id', $category->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('blog_categories', 'slug')) {
            return;
        }

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
