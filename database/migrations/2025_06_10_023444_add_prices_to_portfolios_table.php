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
        Schema::table('portfolios', function (Blueprint $table) {
            $table->decimal('margin')->default(0);
            $table->decimal('vat_rate')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn(['margin', 'vat_rate']);
        });
    }
};
