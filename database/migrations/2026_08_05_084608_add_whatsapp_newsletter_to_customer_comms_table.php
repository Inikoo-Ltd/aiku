<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $field = 'whatsapp_newsletter';

            // Add subscription boolean field
            $table->boolean('is_subscribed_to_' . $field)->default(false)->index();

            // Add unsubscribe tracking fields
            $table->dateTimeTz($field . '_unsubscribed_at')->nullable()->index();
            $table->string($field . '_unsubscribed_author_type')->nullable()->comment('Customer|User');
            $table->string($field . '_unsubscribed_author_id')->nullable();
            $table->string($field . '_unsubscribed_origin_type')->nullable();
            $table->string($field . '_unsubscribed_origin_id')->nullable();

            // Add indexes for author and origin tracking
            $table->index([$field . '_unsubscribed_author_type', $field . '_unsubscribed_author_id']);
            $table->index([$field . '_unsubscribed_origin_type', $field . '_unsubscribed_origin_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $field = 'whatsapp_newsletter';

            // Drop indexes first
            $table->dropIndex([$field . '_unsubscribed_author_type', $field . '_unsubscribed_author_id']);
            $table->dropIndex([$field . '_unsubscribed_origin_type', $field . '_unsubscribed_origin_id']);

            // Drop columns
            $table->dropColumn('is_subscribed_to_' . $field);
            $table->dropColumn($field . '_unsubscribed_at');
            $table->dropColumn($field . '_unsubscribed_author_type');
            $table->dropColumn($field . '_unsubscribed_author_id');
            $table->dropColumn($field . '_unsubscribed_origin_type');
            $table->dropColumn($field . '_unsubscribed_origin_id');
        });
    }
};
