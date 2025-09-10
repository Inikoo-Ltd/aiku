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
        Schema::table('master_assets', function (Blueprint $table) {
            $table->string('description_title')->nullable();
            $table->text('description_extra')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn(['description_title', 'description_extra']);
        });
    }
};
