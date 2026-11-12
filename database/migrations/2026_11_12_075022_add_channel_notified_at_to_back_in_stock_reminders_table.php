<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('back_in_stock_reminders', function (Blueprint $table) {
            $table->dateTimeTz('email_notified_at')->nullable()->index();
            $table->dateTimeTz('whatsapp_notified_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('back_in_stock_reminders', function (Blueprint $table) {
            $table->dropColumn(['email_notified_at', 'whatsapp_notified_at']);
        });
    }
};
