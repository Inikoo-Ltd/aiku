<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('buyer_id')->nullable()->index();
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('sample_approved_at')->nullable();
            $table->timestampTz('produced_at')->nullable();
            $table->timestampTz('qc_passed_at')->nullable();
            $table->timestampTz('handed_over_at')->nullable();
        });

        DB::statement("
            UPDATE purchase_orders
            SET buyer_id = audits.user_id
            FROM audits
            WHERE audits.auditable_type = 'PurchaseOrder'
              AND audits.auditable_id = purchase_orders.id
              AND audits.event = 'created'
              AND audits.user_type = 'User'
              AND audits.user_id IS NOT NULL
              AND purchase_orders.buyer_id IS NULL
        ");

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('production_lead_days');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->smallInteger('production_lead_days')->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
            $table->dropColumn(['buyer_id', 'sample_approved_at', 'produced_at', 'qc_passed_at', 'handed_over_at']);
        });
    }
};
