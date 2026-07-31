<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_menu_items', function (Blueprint $table) {
            $table->id();
            $table->json('label')->nullable();
            $table->string('url')->default('#');
            $table->string('target')->default('_self');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            // Distinguishes the header CTA button from the ordinary nav links.
            $table->boolean('is_cta')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'is_cta', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_menu_items');
    }
};
