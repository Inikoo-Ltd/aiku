<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // For PostgreSQL, we need to drop the check constraint first and add new one
            $table->dropColumn('type');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->enum('type', [
                'annual',
                'medical',
                'unpaid',
                'halfday-morning',
                'halfday-afternoon',
                'training',
                'leave-of-absence',
                'compassionate',
                'parental',
                'sabbatical'
            ])->default('annual');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->enum('type', ['annual', 'medical', 'unpaid'])->default('annual');
        });
    }
};
