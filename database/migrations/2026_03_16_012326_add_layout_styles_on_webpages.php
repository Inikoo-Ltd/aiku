<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->unsignedInteger('layout_style')->default(1);
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn(['layout_style']);
        });
    }
};
