<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issued certificates.
     *
     * The reference used to be derived on the fly, which meant publishing a new
     * lesson silently invalidated every certificate already printed or posted
     * to a profile. Recording the issue makes a reference permanent, and gives
     * /verify/{reference} something to answer from.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->unsignedInteger('lessons_count'); // lessons in the category when issued
            $table->timestamp('completed_at');        // the last lesson's completion
            $table->timestamps();                     // created_at = issued

            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
