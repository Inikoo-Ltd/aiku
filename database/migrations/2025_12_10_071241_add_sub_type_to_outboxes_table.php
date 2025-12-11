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
            $table->string('sub_type')->nullable()->index()->comment('Grouping outbox example: reorder-reminder');
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
            if (Schema::hasColumn('outboxes', 'sub_type')) {
                $table->dropColumn('sub_type');
            }
        });
    }
};
