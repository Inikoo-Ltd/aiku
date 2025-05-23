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
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('picker_user_id')->nullable()->index();
            $table->foreign('picker_user_id')->references('id')->on('users');
            $table->unsignedSmallInteger('packer_user_id')->nullable()->index();
            $table->foreign('packer_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    Schema::table('delivery_notes', function (Blueprint $table) {
        $table->dropForeign(['picker_user_id']);
        $table->dropIndex(['picker_user_id']);
        $table->dropColumn('picker_user_id');

        $table->dropForeign(['packer_user_id']);
        $table->dropIndex(['packer_user_id']);
        $table->dropColumn('packer_user_id');
    });
    }
};
