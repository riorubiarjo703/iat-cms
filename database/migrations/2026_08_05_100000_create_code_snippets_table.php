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

            // The exact shape of the renderer's one query per request.
            $table->index(['is_active', 'position', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_snippets');
    }
};
