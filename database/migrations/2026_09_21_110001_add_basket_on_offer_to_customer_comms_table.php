<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $outboxField = 'basket_on_offer';

            $table->boolean('is_subscribed_to_' . $outboxField)->default(true)->index();

            $table->dateTimeTz($outboxField . '_unsubscribed_at')->nullable()->index();
            $table->string($outboxField . '_unsubscribed_author_type')->nullable()->comment('Customer|User');
            $table->string($outboxField . '_unsubscribed_author_id')->nullable();
            $table->string($outboxField . '_unsubscribed_origin_type')->nullable()->comment('EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)');
            $table->string($outboxField . '_unsubscribed_origin_id')->nullable();

            $table->index([$outboxField . '_unsubscribed_author_type', $outboxField . '_unsubscribed_author_id']);
            $table->index([$outboxField . '_unsubscribed_origin_type', $outboxField . '_unsubscribed_origin_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $outboxField = 'basket_on_offer';

            $table->dropIndex([$outboxField . '_unsubscribed_author_type', $outboxField . '_unsubscribed_author_id']);
            $table->dropIndex([$outboxField . '_unsubscribed_origin_type', $outboxField . '_unsubscribed_origin_id']);

            $table->dropColumn('is_subscribed_to_' . $outboxField);
            $table->dropColumn($outboxField . '_unsubscribed_at');
            $table->dropColumn($outboxField . '_unsubscribed_author_type');
            $table->dropColumn($outboxField . '_unsubscribed_author_id');
            $table->dropColumn($outboxField . '_unsubscribed_origin_type');
            $table->dropColumn($outboxField . '_unsubscribed_origin_id');
        });
    }
};
