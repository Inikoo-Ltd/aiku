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
        Schema::table('pickings', function (Blueprint $table) {
            $table->renameColumn('picker_id', 'picker_user_id');
            $table->dropColumn('state');
            $table->dropColumn('status');
            $table->dropColumn('quantity_required');
            $table->dropColumn('queued_at');
            $table->dropColumn('picking_at');
            $table->dropColumn('picking_blocked_at');
            $table->dropColumn('done_at');
            $table->renameColumn('quantity_picked', 'quantity');
            $table->string('type')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->renameColumn('picker_user_id', 'picker_id');
            $table->string('state')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->integer('quantity_required')->nullable();
            $table->renameColumn('quantity', 'quantity_picked');
            $table->dropColumn('type');
            $table->timestampTz('queued_at');
            $table->timestampTz('picking_at');
            $table->timestampTz('picking_blocked_at');
            $table->timestampTz('done_at');
        });
    }
};
