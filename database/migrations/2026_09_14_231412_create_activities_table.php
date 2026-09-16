<?php

use App\Enums\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            // On Postgres, enum() compiles to a varchar plus a CHECK constraint
            // rather than a native PG enum type. That is the easier of the two:
            // widening the allowed values later is an ordinary migration
            // instead of an ALTER TYPE dance.
            $table->enum('location', array_column(Location::cases(), 'value'));

            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('min_people')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Soft-delete style flag from the brief: activities are retired,
            // never deleted, so historic picks keep pointing at something real.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The picking screen always filters on both of these.
            $table->index(['is_active', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
