<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outboxes', function (Blueprint $table) {
            $table->unsignedInteger('days_after')->nullable()->comment('Days after last invoice to send reorder reminder mail');
            $table->timeTz('send_time')->nullable()->comment('Time to send reorder reminder mail');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outboxes', function (Blueprint $table) {
            if (Schema::hasColumn('outboxes', 'days_after')) {
                $table->dropColumn('days_after');
            }
            if (Schema::hasColumn('outboxes', 'send_time')) {
                $table->dropColumn('send_time');
            }
        });
    }
};
