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
        Schema::table('chat_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_sessions', 'active_user_language_id')) {
                $table->smallInteger('active_user_language_id')->nullable();
                $table->foreign('active_user_language_id', 'chat_sessions_active_user_lang_fk')
                    ->references('id')
                    ->on('languages');
            }

            if (!Schema::hasColumn('chat_sessions', 'user_language_id')) {
                $table->smallInteger('user_language_id')->nullable();
                $table->foreign('user_language_id', 'chat_sessions_user_language_id_fk')
                    ->references('id')
                    ->on('languages');
            }

            if (!Schema::hasColumn('chat_sessions', 'agent_language_id')) {
                $table->smallInteger('agent_language_id')->nullable()->default(68);
                $table->foreign('agent_language_id', 'chat_sessions_agent_language_id_fk')
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
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropForeign('chat_sessions_active_user_lang_fk');
            $table->dropColumn('active_user_language_id');

            $table->dropForeign('chat_sessions_user_language_id_fk');
            $table->dropColumn('user_language_id');

            $table->dropForeign('chat_sessions_agent_language_id_fk');
            $table->dropColumn('agent_language_id');
        });
    }
};
