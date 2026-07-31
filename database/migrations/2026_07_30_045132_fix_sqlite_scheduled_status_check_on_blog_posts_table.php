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
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable('blog_posts')) {
            return;
        }

        $this->rebuildBlogPostsTable("'draft', 'published', 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasTable('blog_posts')) {
            return;
        }

        DB::table('blog_posts')
            ->where('status', 'scheduled')
            ->update(['status' => 'draft']);

        $this->rebuildBlogPostsTable("'draft', 'published'");
    }

    protected function rebuildBlogPostsTable(string $allowedStatuses): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement(<<<SQL
            CREATE TABLE blog_posts_temp (
                id integer primary key autoincrement not null,
                title varchar not null,
                slug varchar not null,
                content text not null,
                excerpt text,
                featured_image varchar,
                seo_title varchar,
                seo_description text,
                seo_keywords text,
                status varchar check (status in ({$allowedStatuses})) not null default 'published',
                published_at datetime,
                blog_category_id integer,
                tags text,
                created_at datetime,
                updated_at datetime,
                foreign key(blog_category_id) references blog_categories(id) on delete set null
            )
        SQL);

        DB::statement(<<<SQL
            INSERT INTO blog_posts_temp (
                id, title, slug, content, excerpt, featured_image, seo_title, seo_description,
                seo_keywords, status, published_at, blog_category_id, tags, created_at, updated_at
            )
            SELECT
                id, title, slug, content, excerpt, featured_image, seo_title, seo_description,
                seo_keywords, status, published_at, blog_category_id, tags, created_at, updated_at
            FROM blog_posts
        SQL);

        DB::statement('DROP TABLE blog_posts');
        DB::statement('ALTER TABLE blog_posts_temp RENAME TO blog_posts');
        DB::statement('CREATE UNIQUE INDEX blog_posts_slug_unique ON blog_posts (slug)');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
