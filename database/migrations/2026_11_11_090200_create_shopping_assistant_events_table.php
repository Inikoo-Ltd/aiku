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
        Schema::create('shopping_assistant_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->index();
            $table->foreign('shopping_assistant_conversation_id')->references('id')->on('shopping_assistant_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('shopping_assistant_message_id')->nullable()->index();
            $table->string('type')->index()->comment('shown, clicked, added');
            $table->unsignedInteger('product_id')->index();
            $table->string('group')->nullable()->comment('must_haves, add_ons');
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('reason_code')->nullable();
            $table->string('reason')->nullable()->comment('the line shown to the customer');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->decimal('quantity', 16, 3)->nullable();
            $table->timestampTz('created_at');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_events');
    }
};
