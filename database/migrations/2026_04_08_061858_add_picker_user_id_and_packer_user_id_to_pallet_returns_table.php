<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->unsignedSmallInteger('picker_user_id')->nullable()->index();
            $table->foreign('picker_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedSmallInteger('packer_user_id')->nullable()->index();
            $table->foreign('packer_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropForeign(['picker_user_id']);
            $table->dropForeign(['packer_user_id']);

            $table->dropColumn(['picker_user_id', 'packer_user_id']);
        });
    }
};
