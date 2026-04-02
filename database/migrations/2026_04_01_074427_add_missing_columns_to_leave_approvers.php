<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leave_approvers', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('leave_approvers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('name');
        });
    }
};
