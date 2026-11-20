<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->unsignedInteger('compliance_ok_by_id')->nullable();
            $table->foreign('compliance_ok_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('compliance_ok_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_artworks', function (Blueprint $table) {
            $table->dropForeign(['compliance_ok_by_id']);
            $table->dropColumn(['compliance_ok_by_id', 'compliance_ok_at']);
        });
    }
};
