<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shopping_assistant_dispatch_outlooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->unsignedInteger('order_id')->index();
            $table->unsignedInteger('delivery_note_id')->nullable();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->nullable()->index();
            $table->string('kind');
            $table->date('estimated_from')->nullable();
            $table->date('estimated_to')->nullable();
            $table->unsignedInteger('staff_task_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_dispatch_outlooks');
    }
};
