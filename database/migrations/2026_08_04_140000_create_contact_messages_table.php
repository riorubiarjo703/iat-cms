<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enquiries submitted from the contact page.
 *
 * Stored rather than only emailed: mail silently fails, gets filtered, and
 * leaves no record that a prospective tenant ever wrote in. The row is the
 * source of truth and any notification is a copy of it.
 *
 * Deliberately not recorded: IP address and user agent. Neither is needed to
 * answer an enquiry, and both turn a leasing form into a personal-data store
 * with retention obligations attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            // Free text, not an enum: the options are editor-configurable on
            // the block, so a constraint here would go stale the moment
            // someone adds an enquiry type in the admin.
            $table->string('subject')->nullable();
            $table->text('message');
            // Which language the sender was reading, so a reply can be written
            // in the language they wrote to us in.
            $table->string('locale', 5)->default('en');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The inbox lists unread first, then newest — this covers both.
            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
