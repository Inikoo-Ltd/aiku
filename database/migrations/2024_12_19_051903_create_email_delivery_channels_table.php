<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_delivery_channels', function (Blueprint $table) {
            $table->id();
            $table->string('model_type')->comment('Mailshot, EmailBulkRun');
            $table->unsignedInteger('model_id');
            $table->unsignedInteger('number_emails');
            $table->string('state')->default(EmailDeliveryChannelStateEnum::READY->value);
            $table->dateTimeTz('start_sending_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();

            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_channels');
    }
};
