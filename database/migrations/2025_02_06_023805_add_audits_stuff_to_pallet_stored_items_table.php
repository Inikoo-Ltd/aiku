<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->unsignedInteger('number_audits')->default(0);
            $table->dateTimeTz('last_audit_at')->nullable();
            $table->unsignedInteger('last_stored_item_audit_delta_id')->nullable()->index();
            $table->foreign('last_stored_item_audit_delta_id')->references('id')->on('stored_item_audit_deltas')->nullOnDelete();
            $table->unsignedInteger('last_stored_item_audit_id')->nullable()->index();
            $table->foreign('last_stored_item_audit_id')->references('id')->on('stored_item_audits')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->dropForeign(['last_stored_item_audit_id']);
            $table->dropForeign(['last_stored_item_audit_delta_id']);
            $table->dropColumn('number_audits');
            $table->dropColumn('last_audit_at');
            $table->dropColumn('last_stored_item_audit_id');
            $table->dropColumn('last_stored_item_audit_delta_id');
        });
    }
};
