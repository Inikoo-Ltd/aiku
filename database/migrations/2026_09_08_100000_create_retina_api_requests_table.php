<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('retina_api_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels');
            $table->unsignedInteger('web_user_id')->nullable();
            $table->foreign('web_user_id')->references('id')->on('web_users');
            $table->unsignedBigInteger('personal_access_token_id')->nullable();
            $table->string('route_name')->nullable()->index();
            $table->string('method', 8);
            $table->string('path');
            $table->jsonb('route_parameters')->nullable();
            $table->jsonb('payload')->nullable();
            $table->unsignedSmallInteger('status')->index();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('response_id')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retina_api_requests');
    }
};
