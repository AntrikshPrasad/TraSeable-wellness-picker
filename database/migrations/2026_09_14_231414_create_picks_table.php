<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wellness_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Nobody can pick the same activity twice to inflate its slice of
            // the wheel. The three-pick limit itself lives in application code;
            // this stops the one abuse the database can see on its own.
            $table->unique(['wellness_session_id', 'user_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picks');
    }
};
