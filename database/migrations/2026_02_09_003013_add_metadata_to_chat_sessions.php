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
        Schema::table('chat_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_sessions', 'metadata')) {
                $table->json('metadata')->nullable()->after('agent_language_id');
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
            if (Schema::hasColumn('chat_sessions', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
