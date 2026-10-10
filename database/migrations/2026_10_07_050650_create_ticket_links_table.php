<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ticket_links', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->unsignedInteger('linked_ticket_id')->index();
            $table->foreign('linked_ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('created_by_id')->nullable();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['ticket_id', 'linked_ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_links');
    }
};
