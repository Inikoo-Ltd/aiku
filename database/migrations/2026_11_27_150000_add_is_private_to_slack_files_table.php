<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_files', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->comment('a copy of a file of a private channel or DM, served to its members only and never mixed with the public copy of the same Slack file');
            $table->dropUnique(['slack_id']);
            $table->unique(['slack_id', 'is_private']);
        });
    }

    public function down(): void
    {
        Schema::table('slack_files', function (Blueprint $table) {
            $table->dropUnique(['slack_id', 'is_private']);
            $table->dropColumn('is_private');
            $table->unique('slack_id');
        });
    }
};
