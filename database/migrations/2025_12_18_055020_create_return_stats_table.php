<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Create return_stats table for return statistics
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('return_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('return_id')->index();
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();

            $table->unsignedSmallInteger('number_items')->default(0)->comment('current number of items');
            $table->unsignedSmallInteger('number_items_state_pending')->default(0);
            $table->unsignedSmallInteger('number_items_state_received')->default(0);
            $table->unsignedSmallInteger('number_items_state_inspecting')->default(0);
            $table->unsignedSmallInteger('number_items_state_accepted')->default(0);
            $table->unsignedSmallInteger('number_items_state_rejected')->default(0);
            $table->unsignedSmallInteger('number_items_state_restocked')->default(0);

            $table->decimal('total_quantity_expected', 16, 3)->default(0);
            $table->decimal('total_quantity_received', 16, 3)->default(0);
            $table->decimal('total_quantity_accepted', 16, 3)->default(0);
            $table->decimal('total_quantity_rejected', 16, 3)->default(0);
            $table->decimal('total_quantity_restocked', 16, 3)->default(0);

            $table->decimal('total_refund_amount', 16)->default(0);
            $table->decimal('total_org_refund_amount', 16)->default(0);
            $table->decimal('total_grp_refund_amount', 16)->default(0);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_stats');
    }
};
