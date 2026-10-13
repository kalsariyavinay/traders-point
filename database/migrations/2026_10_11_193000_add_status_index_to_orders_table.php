<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin orders screen filters by status and sorts by date on its
     * default view, and sums the amount for the revenue figure. The only
     * existing index leads with user_id, which that query cannot use, so it
     * was a full scan plus a filesort.
     *
     * amount is the third column on purpose: it makes the index covering for
     * the revenue SUM, so that figure is answered from the index alone.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'amount'], 'orders_status_created_at_amount_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_at_amount_index');
        });
    }
};
