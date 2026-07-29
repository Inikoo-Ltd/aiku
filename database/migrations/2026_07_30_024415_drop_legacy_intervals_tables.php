<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('asset_ordering_intervals');
        Schema::dropIfExists('asset_sales_intervals');
        Schema::dropIfExists('collection_ordering_intervals');
        Schema::dropIfExists('collection_sales_intervals');
        Schema::dropIfExists('email_bulk_run_intervals');
        Schema::dropIfExists('email_ongoing_run_intervals');
        Schema::dropIfExists('group_mailshots_intervals');
        Schema::dropIfExists('group_ordering_intervals');
        Schema::dropIfExists('group_outbox_cold_emails_intervals');
        Schema::dropIfExists('group_outbox_customer_notification_intervals');
        Schema::dropIfExists('group_outbox_marketing_intervals');
        Schema::dropIfExists('group_outbox_marketing_notification_intervals');
        Schema::dropIfExists('group_outbox_newsletter_intervals');
        Schema::dropIfExists('group_outbox_push_intervals');
        Schema::dropIfExists('group_outbox_test_intervals');
        Schema::dropIfExists('group_outbox_user_notification_intervals');
        Schema::dropIfExists('group_sales_intervals');
        Schema::dropIfExists('group_sysadmin_intervals');
        Schema::dropIfExists('invoice_category_ordering_intervals');
        Schema::dropIfExists('invoice_category_sales_intervals');
        Schema::dropIfExists('master_asset_ordering_intervals');
        Schema::dropIfExists('master_asset_sales_intervals');
        Schema::dropIfExists('master_collection_ordering_intervals');
        Schema::dropIfExists('master_collection_sales_intervals');
        Schema::dropIfExists('master_product_category_ordering_intervals');
        Schema::dropIfExists('master_product_category_sales_intervals');
        Schema::dropIfExists('master_shop_ordering_intervals');
        Schema::dropIfExists('master_shop_sales_intervals');
        Schema::dropIfExists('master_variant_ordering_intervals');
        Schema::dropIfExists('master_variant_sales_intervals');
        Schema::dropIfExists('org_post_room_intervals');
        Schema::dropIfExists('org_stock_family_intervals');
        Schema::dropIfExists('org_stock_family_sales_intervals');
        Schema::dropIfExists('org_stock_intervals');
        Schema::dropIfExists('org_stock_sales_intervals');
        Schema::dropIfExists('organisation_mailshots_intervals');
        Schema::dropIfExists('organisation_ordering_intervals');
        Schema::dropIfExists('organisation_outbox_cold_emails_intervals');
        Schema::dropIfExists('organisation_outbox_customer_notification_intervals');
        Schema::dropIfExists('organisation_outbox_marketing_intervals');
        Schema::dropIfExists('organisation_outbox_marketing_notification_intervals');
        Schema::dropIfExists('organisation_outbox_newsletter_intervals');
        Schema::dropIfExists('organisation_outbox_push_intervals');
        Schema::dropIfExists('organisation_outbox_test_intervals');
        Schema::dropIfExists('organisation_outbox_user_notification_intervals');
        Schema::dropIfExists('organisation_sales_intervals');
        Schema::dropIfExists('outbox_intervals');
        Schema::dropIfExists('platform_sales_intervals');
        Schema::dropIfExists('platform_shop_sales_intervals');
        Schema::dropIfExists('post_room_intervals');
        Schema::dropIfExists('product_category_ordering_intervals');
        Schema::dropIfExists('product_category_sales_intervals');
        Schema::dropIfExists('shop_mailshots_intervals');
        Schema::dropIfExists('shop_ordering_intervals');
        Schema::dropIfExists('shop_outbox_cold_emails_intervals');
        Schema::dropIfExists('shop_outbox_customer_notification_intervals');
        Schema::dropIfExists('shop_outbox_marketing_intervals');
        Schema::dropIfExists('shop_outbox_marketing_notification_intervals');
        Schema::dropIfExists('shop_outbox_newsletter_intervals');
        Schema::dropIfExists('shop_outbox_push_intervals');
        Schema::dropIfExists('shop_sales_intervals');
        Schema::dropIfExists('stock_family_intervals');
        Schema::dropIfExists('stock_family_sales_intervals');
        Schema::dropIfExists('stock_intervals');
        Schema::dropIfExists('stock_sales_intervals');
        Schema::dropIfExists('variant_sales_intervals');
        Schema::dropIfExists('variant_sales_ordering_intervals');
    }


    public function down(): void
    {
        //
    }
};
