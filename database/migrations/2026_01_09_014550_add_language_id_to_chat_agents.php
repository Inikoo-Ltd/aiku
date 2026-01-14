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
        Schema::table('chat_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_agents', 'language_id')) {
                $table->smallInteger('language_id')->nullable()->default(68);

                $table->foreign('language_id', 'chat_agents_language_fk')
                    ->references('id')
                    ->on('languages');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chat_agents', function (Blueprint $table) {
            $table->dropForeign('chat_agents_language_fk');
            $table->dropColumn('language_id');
        });
    }
};
