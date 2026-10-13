<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the learner stopped, so the player can pick the lesson back up.
     * Reset to 0 when a video is watched to the end.
     */
    public function up(): void
    {
        Schema::table('video_views', function (Blueprint $table) {
            $table->unsignedInteger('progress_seconds')->default(0)->after('video_id');
        });
    }

    public function down(): void
    {
        Schema::table('video_views', function (Blueprint $table) {
            $table->dropColumn('progress_seconds');
        });
    }
};
