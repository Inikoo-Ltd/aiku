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
            $table->dateTimeTz('last_sent_at')->nullable()->comment('Last time this outbox was sent');
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
            if (Schema::hasColumn('outboxes', 'last_sent_at')) {
                $table->dropColumn('last_sent_at');
            }
        });
    }
};
