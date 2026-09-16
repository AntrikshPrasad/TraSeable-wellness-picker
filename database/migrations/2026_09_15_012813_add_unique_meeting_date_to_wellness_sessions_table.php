<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One session per Friday.
     *
     * The partial index added with the table only stops a second *open*
     * session. That left a gap: once a Friday had been decided, opening
     * another session for the same Friday was allowed, and the app would then
     * have two rows competing to be "the current session". This closes it.
     */
    public function up(): void
    {
        Schema::table('wellness_sessions', function (Blueprint $table) {
            $table->unique('meeting_date');
        });
    }

    public function down(): void
    {
        Schema::table('wellness_sessions', function (Blueprint $table) {
            $table->dropUnique(['meeting_date']);
        });
    }
};
