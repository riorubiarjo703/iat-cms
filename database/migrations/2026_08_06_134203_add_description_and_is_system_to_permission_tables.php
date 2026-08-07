<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');

            // A column rather than a name check: protecting super_admin by
            // comparing its name would stop protecting it the moment somebody
            // renamed it, and renaming is allowed.
            $table->boolean('is_system')->default(false);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('is_system')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
