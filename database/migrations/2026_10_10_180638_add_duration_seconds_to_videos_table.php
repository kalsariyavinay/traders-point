<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runtime of the lesson, entered by the admin.
     *
     * Deliberately NOT reported by the player: completion is judged against
     * this number, so letting a browser set it would let anyone mint a
     * certificate. Null means completion cannot be judged yet.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
