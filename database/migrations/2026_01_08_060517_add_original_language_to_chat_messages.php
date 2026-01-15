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
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'original_language_id')) {
                $table->smallInteger('original_language_id')->nullable();
                $table->foreign('original_language_id', 'chat_messages_original_language_fk')
                    ->references('id')
                    ->on('languages');
            }
            if (!Schema::hasColumn('chat_messages', 'original_text')) {
                $table->text('original_text')->nullable();
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
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign('chat_messages_original_language_fk');
            $table->dropColumn(['original_language_id', 'original_text']);
        });
    }
};
