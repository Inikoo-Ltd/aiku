<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 00:03:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_comms', function (Blueprint $table) {
            $outboxFields = [
                'newsletter',
                'marketing',
                'abandoned_cart',
                'reorder_reminder',
                'basket_low_stock',
                'basket_reminder',
            ];

            $table->increments('id');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->boolean('is_suspended')->default(false)->index()->comment('Suspend communication with customer because of spam or bounces');
            $table->dateTimeTz('suspended_at')->nullable()->index();
            $table->string('suspended_cause')->nullable()->index();

            foreach ($outboxFields as $outboxField) {
                $table->boolean('is_subscribed_to_'.$outboxField)->index();
            }

            foreach ($outboxFields as $outboxField) {
                $table->dateTimeTz($outboxField.'_unsubscribed_at')->nullable()->index();
                $table->string($outboxField.'_unsubscribed_author_type')->nullable()->index()->comment('Customer|User');
                $table->string($outboxField.'_unsubscribed_author_id')->nullable();
                $table->string($outboxField.'_unsubscribed_origin_type')->nullable()->comment('EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)');
                $table->string($outboxField.'_unsubscribed_origin_id')->nullable();
            }

            $table->timestampsTz();

            foreach ($outboxFields as $outboxField) {
                $table->index([$outboxField.'_unsubscribed_author_type', $outboxField.'_unsubscribed_author_id']);
                $table->index([$outboxField.'_unsubscribed_origin_type', $outboxField.'_unsubscribed_origin_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_comms');
    }
};
