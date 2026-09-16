<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One activity per name.
     *
     * The name is all anyone sees on the wheel, so two activities sharing one
     * are indistinguishable at the moment it matters. Both add forms already
     * validate this; the index is what makes it true regardless of races,
     * seeders, or somebody editing the table by hand.
     */
    public function up(): void
    {
        $this->refuseIfDuplicatesExist();

        Schema::table('activities', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['activities_name_unique']);
        });
    }

    /**
     * Postgres would refuse the index anyway, but with a message about an
     * index rather than about the data. Fail first, naming the duplicates, so
     * whoever runs this on the server knows what to go and fix.
     */
    private function refuseIfDuplicatesExist(): void
    {
        $duplicates = DB::table('activities')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('name');

        if ($duplicates->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'Cannot add a unique index: these activity names appear more than once - '
            .$duplicates->implode(', ')
            .'. Rename or retire the copies, then run the migration again.'
        );
    }
};
