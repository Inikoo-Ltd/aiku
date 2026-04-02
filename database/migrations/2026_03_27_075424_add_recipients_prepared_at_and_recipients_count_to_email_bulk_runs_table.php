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
        Schema::table('email_bulk_runs', function (Blueprint $table) {
            $table->unsignedMediumInteger('recipients_count')
                ->nullable();
            $table->dateTimeTz('recipients_prepared_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('email_bulk_runs', function (Blueprint $table) {
            $table->dropColumn('recipients_count');
            $table->dropColumn('recipients_prepared_at');
        });
    }
};
