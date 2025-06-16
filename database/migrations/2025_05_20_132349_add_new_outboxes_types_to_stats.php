<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('org_post_room_stats', 'number_outboxes_type_new_pallet_return_from_customer')) {
            Schema::table('org_post_room_stats', function (Blueprint $table) {
                $table->smallInteger('number_outboxes_type_new_pallet_return_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_new_pallet_delivery_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_delivery_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_return_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_deleted')->default(0);

            });
        }

        if (!Schema::hasColumn('group_comms_stats', 'number_outboxes_type_new_pallet_return_from_customer')) {
            Schema::table('group_comms_stats', function (Blueprint $table) {
                $table->smallInteger('number_outboxes_type_new_pallet_return_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_new_pallet_delivery_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_delivery_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_return_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_deleted')->default(0);
            });
        }

        if (!Schema::hasColumn('organisation_comms_stats', 'number_outboxes_type_new_pallet_return_from_customer')) {
            Schema::table('organisation_comms_stats', function (Blueprint $table) {
                $table->smallInteger('number_outboxes_type_new_pallet_return_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_new_pallet_delivery_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_delivery_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_return_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_deleted')->default(0);
            });
        }

        if (!Schema::hasColumn('shop_comms_stats', 'number_outboxes_type_new_pallet_return_from_customer')) {
            Schema::table('shop_comms_stats', function (Blueprint $table) {
                $table->smallInteger('number_outboxes_type_new_pallet_return_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_new_pallet_delivery_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_delivery_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_return_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_deleted')->default(0);
            });
        }

        if (!Schema::hasColumn('post_room_stats', 'number_outboxes_type_new_pallet_return_from_customer')) {
            Schema::table('post_room_stats', function (Blueprint $table) {
                $table->smallInteger('number_outboxes_type_new_pallet_return_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_new_pallet_delivery_from_customer')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_delivery_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_return_deleted')->default(0);
                $table->smallInteger('number_outboxes_type_pallet_deleted')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('org_post_room_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_pallet_return_from_customer');
            $table->dropColumn('number_outboxes_type_new_pallet_delivery_from_customer');
            $table->dropColumn('number_outboxes_type_pallet_delivery_deleted');
            $table->dropColumn('number_outboxes_type_pallet_return_deleted');
            $table->dropColumn('number_outboxes_type_pallet_deleted');
        });
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_pallet_return_from_customer');
            $table->dropColumn('number_outboxes_type_new_pallet_delivery_from_customer');
            $table->dropColumn('number_outboxes_type_pallet_delivery_deleted');
            $table->dropColumn('number_outboxes_type_pallet_return_deleted');
            $table->dropColumn('number_outboxes_type_pallet_deleted');
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_pallet_return_from_customer');
            $table->dropColumn('number_outboxes_type_new_pallet_delivery_from_customer');
            $table->dropColumn('number_outboxes_type_pallet_delivery_deleted');
            $table->dropColumn('number_outboxes_type_pallet_return_deleted');
            $table->dropColumn('number_outboxes_type_pallet_deleted');
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_pallet_return_from_customer');
            $table->dropColumn('number_outboxes_type_new_pallet_delivery_from_customer');
            $table->dropColumn('number_outboxes_type_pallet_delivery_deleted');
            $table->dropColumn('number_outboxes_type_pallet_return_deleted');
            $table->dropColumn('number_outboxes_type_pallet_deleted');
        });
        Schema::table('post_room_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_pallet_return_from_customer');
            $table->dropColumn('number_outboxes_type_new_pallet_delivery_from_customer');
            $table->dropColumn('number_outboxes_type_pallet_delivery_deleted');
            $table->dropColumn('number_outboxes_type_pallet_return_deleted');
            $table->dropColumn('number_outboxes_type_pallet_deleted');
        });
    }
};
