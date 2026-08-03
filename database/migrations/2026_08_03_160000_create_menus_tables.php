<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menus are templates; locations are assignments. A menu exists on its own and
 * is optionally shown in one location, which is why `location` lives on the
 * menu rather than in a join table: a location displays exactly one menu, and
 * a unique index makes that impossible to violate. Postgres permits many NULLs
 * in a unique index, so any number of menus can stay unassigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            // Self-referencing: deleting a parent takes its children with it,
            // so no orphan can be left pointing at a row that is gone.
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();

            $table->string('type')->default('custom');
            $table->json('label');

            // Custom links carry a URL; linked items resolve theirs from the
            // record, so the URL follows a slug change instead of going stale.
            $table->string('url')->nullable();
            $table->nullableMorphs('linkable');

            $table->string('target')->default('_self');
            // The header's call-to-action is an ordinary item wearing a flag,
            // so it can be reordered, translated and removed like any other.
            $table->boolean('is_cta')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
