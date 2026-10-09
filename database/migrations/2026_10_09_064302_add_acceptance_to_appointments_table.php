<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTimeTz('accepted_at')->nullable();
            $table->dateTimeTz('declined_at')->nullable();
            $table->text('state_reason')->nullable();
        });

        DB::table('appointments')->where('state', 'booked')->update(['state' => 'accepted', 'accepted_at' => DB::raw('created_at')]);
    }


    public function down(): void
    {
        DB::table('appointments')->where('state', 'accepted')->update(['state' => 'booked']);
        DB::table('appointments')->whereIn('state', ['requested', 'declined'])->update(['state' => 'cancelled']);

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'declined_at', 'state_reason']);
        });
    }
};
