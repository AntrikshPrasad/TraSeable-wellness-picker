<?php

use App\Enums\DecisionMethod;
use App\Enums\SessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named wellness_sessions, not sessions, because Laravel's own `sessions`
     * table already exists (created in 0001_01_01_000000_create_users_table
     * for the database session driver).
     */
    public function up(): void
    {
        Schema::create('wellness_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('meeting_date');
            $table->enum('status', array_column(SessionStatus::cases(), 'value'))
                ->default(SessionStatus::Open->value);

            // The result. Nullable until the wheel is spun; nullOnDelete rather
            // than cascade so retiring an activity never erases a past result.
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->enum('decision_method', array_column(DecisionMethod::cases(), 'value'))->nullable();

            // Set when a spin was redone because the weather turned.
            $table->timestamp('rained_off_at')->nullable();

            $table->timestamps();
        });

        // "Only one open session at a time" as a database guarantee rather than
        // a hopeful if-statement. A partial unique index constrains only the
        // rows matching the WHERE clause, so any number of decided/skipped
        // sessions coexist while a second open one is rejected outright.
        // Postgres-specific, which is why the test suite runs on Postgres too.
        DB::statement(
            "CREATE UNIQUE INDEX one_open_wellness_session ON wellness_sessions (status) WHERE status = 'open'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_sessions');
    }
};
