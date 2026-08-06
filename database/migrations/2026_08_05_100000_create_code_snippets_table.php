<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_snippets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('script');
            $table->string('position')->default('head');

            // 0-100 per the design. The column permits 255; the form is the
            // constraint, so the range can be widened without a migration.
            $table->unsignedTinyInteger('priority')->default(10);

            $table->text('code');

            // Operator notes. Never rendered to the site.
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('skip_for_admins')->default(true);
            $table->timestamps();

            // Covers the renderer's actual query: `where is_active = ? order
            // by priority, id`. `position` is not a predicate there — the
            // renderer fetches the whole active set and groups it by position
            // in PHP (see SnippetRenderer::grouped()) rather than filtering
            // by it per call, so this column rides along in the index without
            // being used by that query. Left in place rather than reshaped,
            // since this migration has already run against the dev database.
            $table->index(['is_active', 'position', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_snippets');
    }
};
