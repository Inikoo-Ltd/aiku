<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('employee_bulk_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('sender_id')->nullable()->index();
            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->unsignedInteger('number_recipients')->default(0);
            $table->unsignedInteger('number_pending')->default(0);
            $table->jsonb('attachments')->default('[]');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bulk_emails');
    }
};
