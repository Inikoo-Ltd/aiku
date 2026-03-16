<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::rename('external_email_recipients', 'chat_email_recipients');

        Schema::table('chat_email_recipients', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->index('email');
        });


    }

    public function down(): void
    {
        Schema::rename('chat_email_recipients', 'external_email_recipients');
    }
};
