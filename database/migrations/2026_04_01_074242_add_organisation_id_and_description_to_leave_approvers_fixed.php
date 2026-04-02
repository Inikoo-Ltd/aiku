<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_approvers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('user_id');
            $table->unsignedMediumInteger('organisation_id')->index()->after('description');

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');

            $table->index(['organisation_id', 'sequence_number', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_approvers', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'sequence_number', 'is_active']);
            $table->dropForeign(['organisation_id']);
            $table->dropColumn(['organisation_id', 'description']);
        });
    }
};
