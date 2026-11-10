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
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->boolean('assistant_available')->nullable()->comment('the Ask button was offered; null on rows from before HELP-4608 logging');
            $table->jsonb('suggestions')->nullable()->comment('ids of what Keep exploring returned for this search');
            $table->dateTimeTz('suggestions_viewed_at')->nullable();
        });

        Schema::create('website_search_log_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_search_log_id')->index();
            $table->foreign('website_search_log_id')->references('id')->on('website_search_logs')->cascadeOnDelete();
            $table->unsignedInteger('website_id');
            $table->string('type', 16)->comment('result, suggestion');
            $table->string('kind', 32)->nullable()->comment('suggestion kind, null for a result');
            $table->string('target', 16)->comment('link, button');
            $table->text('url');
            $table->timestampTz('created_at');
            $table->index(['website_id', 'created_at']);
        });

        Schema::create('shopping_assistant_refusals', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id');
            $table->unsignedSmallInteger('organisation_id');
            $table->unsignedInteger('shop_id');
            $table->unsignedInteger('website_id');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->string('reason', 16)->comment('rate_limit, budget');
            $table->text('question')->nullable();
            $table->timestampTz('created_at');
            $table->index(['website_id', 'created_at']);
        });

        Schema::create('shopping_assistant_model_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->index();
            $table->foreign('shopping_assistant_conversation_id')->references('id')->on('shopping_assistant_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_message_id')->nullable();
            $table->unsignedBigInteger('shopping_assistant_message_id')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0)->comment('USD');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('finish_reason')->nullable();
            $table->timestampTz('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_model_calls');
        Schema::dropIfExists('shopping_assistant_refusals');
        Schema::dropIfExists('website_search_log_clicks');
        Schema::table('website_search_logs', function (Blueprint $table) {
            $table->dropColumn(['assistant_available', 'suggestions', 'suggestions_viewed_at']);
        });
    }
};
