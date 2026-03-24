<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('ignore_concurrency_leave_rules')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('ignore_concurrency_leave_rules');
        });
    }
};
