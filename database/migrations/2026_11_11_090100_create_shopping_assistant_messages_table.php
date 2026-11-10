<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shopping_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->index();
            $table->foreign('shopping_assistant_conversation_id')->references('id')->on('shopping_assistant_conversations')->cascadeOnDelete();
            $table->string('role')->comment('customer, assistant');
            $table->text('body')->nullable()->comment('the text the customer wrote or was shown');
            $table->jsonb('tool_calls')->nullable()->comment('one entry per tool call of the turn: name, arguments, product_ids, error, ms');
            $table->jsonb('follow_ups')->nullable();
            $table->jsonb('flags')->nullable()->comment('guardrail events of the turn');
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('model_calls')->default(0);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('feedback')->nullable()->comment('up, down');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_messages');
    }
};
