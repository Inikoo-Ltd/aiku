<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            //
            $table->boolean('is_in_test')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->dropColumn(['is_in_test']);
        });
    }
};
