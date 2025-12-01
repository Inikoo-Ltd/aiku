<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        //
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->jsonb('website_type')->default(json_encode([]));
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        //
        Schema::table('web_block_types', function (Blueprint $table) {
            $table->dropColumn(['website_type', 'deleted_at']);
        });
    }
};
