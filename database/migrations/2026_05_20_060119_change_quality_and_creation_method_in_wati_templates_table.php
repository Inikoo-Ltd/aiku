<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('wati_templates', function (Blueprint $table) {
            $table->string('quality')->nullable()->change();
            $table->string('creation_method')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wati_templates', function (Blueprint $table) {
            $table->integer('quality')->default(0)->change();
            $table->integer('creation_method')->default(0)->change();
        });
    }
};
