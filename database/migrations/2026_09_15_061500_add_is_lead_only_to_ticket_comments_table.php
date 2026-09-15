<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->boolean('is_lead_only')->default(false)->index();
        });

        DB::table('ticket_comments')->where('is_internal', true)->update(['is_lead_only' => true]);
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn('is_lead_only');
        });
    }
};
