<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set once the learner passes 90% of the video's duration. Sticky: starting
     * the lesson again resets the playhead but never the completion.
     */
    public function up(): void
    {
        Schema::table('video_views', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('progress_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('video_views', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
