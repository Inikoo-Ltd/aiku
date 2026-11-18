<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $field = 'whatsapp_notifications';

            $table->boolean('is_subscribed_to_'.$field)->default(false)->index();
            $table->dateTimeTz($field.'_subscribed_at')->nullable();
            $table->string($field.'_subscribed_origin')->nullable();

            $table->dateTimeTz($field.'_unsubscribed_at')->nullable()->index();
            $table->string($field.'_unsubscribed_author_type')->nullable()->comment('Customer|User');
            $table->string($field.'_unsubscribed_author_id')->nullable();
            $table->string($field.'_unsubscribed_origin_type')->nullable();
            $table->string($field.'_unsubscribed_origin_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $field = 'whatsapp_notifications';

            $table->dropColumn([
                'is_subscribed_to_'.$field,
                $field.'_subscribed_at',
                $field.'_subscribed_origin',
                $field.'_unsubscribed_at',
                $field.'_unsubscribed_author_type',
                $field.'_unsubscribed_author_id',
                $field.'_unsubscribed_origin_type',
                $field.'_unsubscribed_origin_id',
            ]);
        });
    }
};
