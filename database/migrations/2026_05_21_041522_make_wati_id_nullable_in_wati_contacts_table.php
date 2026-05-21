<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('wati_contacts', function (Blueprint $table) {
            $table->string('wati_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wati_contacts', function (Blueprint $table) {
            $table->string('wati_id')->nullable(false)->change();
        });
    }
};
