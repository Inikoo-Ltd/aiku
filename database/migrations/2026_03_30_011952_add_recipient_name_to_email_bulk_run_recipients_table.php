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
        Schema::table('email_bulk_run_recipients', function (Blueprint $table) {
            $table->string('recipient_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('email_bulk_run_recipients', function (Blueprint $table) {
            $table->dropColumn('recipient_name');
        });
    }
};
