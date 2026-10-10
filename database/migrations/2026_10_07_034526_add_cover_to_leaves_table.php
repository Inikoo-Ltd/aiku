<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->unsignedMediumInteger('cover_employee_id')->nullable()->index();
            $table->foreign('cover_employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->boolean('cover_has_permissions')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['cover_employee_id']);
            $table->dropColumn(['cover_employee_id', 'cover_has_permissions']);
        });
    }
};
