<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/*
 * Who reads what through MCP SQL. Every MCP SQL query logs in as a PostgreSQL role that is a
 * member of the user's tiers only, so PostgreSQL itself refuses any table or column the tiers
 * were not granted. A user's tiers come from their job positions. Applied to the database by
 * `php artisan mcp:sync-sql-roles`, which every deploy runs after migrating. Patterns are shell
 * globs on table names; a table matched by `never` is granted to no tier whatever else matches it.
 */

return [

    /*
     * The main programmers read everything, `never` and hidden columns included: their queries
     * skip SET ROLE and run as the read-only user itself. Usernames live here, in code, not in env.
     */
    'unrestricted_users' => ['raul', 'aiku'],

    /*
     * Credentials, card tokens, private messages: no tier, not even engineering.
     */
    'never' => [
        'oauth_*', 'personal_access_tokens', 'passkeys', 'password_resets', 'web_user_password_resets',
        'mit_saved_cards', 'fcm_tokens', 'user_push_subscriptions', 'user_failed_log_ins', 'web_user_failed_logins',
        'allegro_users', 'amazon_users', 'ebay_users', 'magento_users', 'shopify_users', 'tiktok_users', 'wix_users', 'woo_commerce_users',
        'shopify_user_has_fulfilments', 'tiktok_user_has_*', 'wc_user_has_products',
        'supplier_users', 'shipper_accounts', 'staff_messages', 'staff_message_*', 'email_archive_messages',
        'telescope_*', 'migrations', 'pg_stat_statements*',
    ],

    /*
     * Tables readable only with some columns left out (column-level GRANT SELECT).
     */
    'hidden_columns' => [
        'users'             => ['password', 'legacy_password', 'remember_token', 'reset_password', 'google2fa_secret'],
        'web_users'         => ['password', 'remember_token', 'reset_password'],
        'websites'          => ['cloudflare_token', 'settings'],
        'competitors'       => ['password'],
        'clocking_machines' => ['kiosk_token'],
        'employees'         => ['salary', 'pin', 'religion', 'identity_document_type', 'identity_document_number', 'identity_document_issued_by', 'insurance_number', 'bank_account_name', 'bank_account_number'],
        'qr_scan_logs'      => ['qr_token'],
        'groups'            => ['settings'],
        'organisations'     => ['settings'],
        'shops'             => ['settings'],
        'shippers'          => ['settings', 'data'],
        'payment_accounts'  => ['data'],
        'manufacture_pay_bands'     => ['hourly_rate'],
        'manufacture_task_sessions' => ['hourly_rate', 'bonus', 'operative_reward_amount', 'pay', 'task_work_cost'],
        'manufacture_tasks'         => ['operative_reward_amount'],
    ],

    /*
     * Hidden columns a tier may read after all. A column-only tier: it grants no tables.
     */
    'shown_columns' => [
        'payroll' => [
            'employees'                 => ['salary', 'bank_account_name', 'bank_account_number'],
            'manufacture_pay_bands'     => ['hourly_rate'],
            'manufacture_task_sessions' => ['hourly_rate', 'bonus', 'operative_reward_amount', 'pay', 'task_work_cost'],
            'manufacture_tasks'         => ['operative_reward_amount'],
        ],
        'hr_records' => [
            'employees' => ['identity_document_type', 'identity_document_number', 'identity_document_issued_by', 'insurance_number'],
        ],
    ],

    /*
     * Tables the broad base globs (*_stats, product_*) would hand to everyone, that belong to
     * one tier instead: per person activity, HR and money totals, customer links.
     */
    'not_base' => [
        'user_*', 'employee_*', 'guest_*', '*_human_resources_stats', '*_accounting_stats', 'payment_*_stats',
        'org_payment_service_provider_stats', 'product_has_exclusive_customers', 'product_last_seens',
    ],

    'tiers' => [

        /*
         * Everyone with SQL: reference data, the catalogue, stock, and the stats and time
         * series behind every dashboard (they carry sales totals, which every shopkeeper sees).
         */
        'base' => [
            'groups', 'organisations', 'shops', 'warehouses', 'warehouse_areas', 'countries', 'country_*', 'currencies', 'currency_exchanges',
            'languages', 'timezones', 'tax_categories', 'tags', 'model_has_tags', 'translation_*',
            'products', 'product_*', 'assets', 'asset_*', 'historic_asset*', 'variants', 'variant_*', 'bundles', 'bundle_items', 'services', 'charges', 'charge_stats', 'rentals',
            'collections', 'collection_*', 'model_has_collections', 'brands', 'brand_*', 'model_has_brands', 'new_products', 'catalogue_*',
            'master_*', 'model_has_master_collections',
            'trade_units', 'trade_unit*', 'model_has_trade_units', 'ingredients', 'tariff_codes', 'barcodes', 'model_has_barcodes',
            'stocks', 'stock_families', 'stock_family_*', 'stock_stats', 'stock_time_series*', 'stock_has_supplier_products',
            'org_stocks', 'org_stock_families', 'org_stock_family_*', 'org_stock_stats', 'org_stock_time_series*', 'org_stock_has_org_supplier_products',
            'packagings', 'packaging_*', 'epr_*', 'leaflets', 'model_has_leaflets', 'boxes', 'artefact*', 'media', 'model_has_media', 'image_short_urls',
            '*_stats', '*_time_series', '*_time_series_records', '*tsr_*', 'sfsr_*', 'osfsr_*', 'pctr_*', 'mpctr_*', 'dashboard_time_series_aggregates',
            'shop_sales_targets', 'sales_target_tips', 'sales_channels', 'shop_has_sales_channels', 'platforms', 'platform_has_clients',
            'job_positions', 'job_position_categories', 'workplaces', 'org_post_rooms', 'post_rooms',
            'offers', 'offer_campaigns', 'offer_allowances',
            'tickets', 'ticket_*', 'staff_tasks', 'staff_task_*',
        ],

        /*
         * Orders and invoices, without the people behind them.
         */
        'sales' => [
            'orders', 'transactions', 'transaction_has_*', 'order_has_*', 'upcoming_transactions', 'pre_orders',
            'invoices', 'invoice_transaction*', 'invoice_has_*', 'invoice_categories',
            'delivery_notes', 'delivery_note_*', 'returns', 'return_*', 'unidentified_returns',
            'portfolios', 'platform_portfolio_logs', 'customer_sales_channels', 'download_portfolio_customer_sales_channel',
            'offer_has_customers', 'purged_orders', 'purges', 'checkout_abandonments', 'shopping_list_items', 'waiting_items',
            'retina_dashboard_basket_adds', 'favourites', 'back_in_stock_reminder*',
        ],

        /*
         * Who the customers are: names, addresses, emails, conversations.
         */
        'customers' => [
            'customers', 'customer_*', 'web_users', 'web_user_logins', 'web_user_requests', 'web_user_has_dispatched_emails',
            'addresses', 'model_has_addresses', 'model_has_fixed_addresses', 'email_addresses', 'prospects', 'prospect_has_dispatched_emails',
            'product_has_exclusive_customers', 'product_last_seens', 'fulfilment_customers', 'luna_clients', 'subscriptions', 'subscription_events', 'preferred_shippings', 'tax_numbers',
        ],

        /*
         * What customers say about the products: chats, reviews, polls, delivery feedback.
         */
        'feedback' => [
            'chat_*', 'meta_chat_*', 'meta_channels', 'meta_message_templates', 'reviews', 'review_*', 'polls', 'poll_*',
            'feedbacks', 'model_has_feedbacks', '*_has_feedback',
        ],

        'warehouse' => [
            'addresses', 'model_has_addresses', 'model_has_fixed_addresses',
            'locations', 'location_*', 'org_stock_movements*', 'org_stock_movement_batches', 'org_stock_histories*', 'org_stock_audit*',
            'organisation_stock_histor*', 'group_stock_histories', 'lost_and_found_stocks', 'stock_transfers', 'debug_stock_updates',
            'pickings', 'picking_*', 'packings', 'picked_bay*', 'trolleys', 'sowings', 'shipments', 'model_has_shipments',
            'shippers', 'shipping_*', 'fulfilments', 'fulfilment_*', 'pallet*', 'stored_item*', 'movement_pallets', 'spaces',
            'recurring_bill*', 'model_has_recurring_bills', 'rental_agreement*', 'batch_codes', 'serial_references', 'scanner_ips',
        ],

        'procurement' => [
            'suppliers', 'supplier_*', 'historic_supplier_product*', 'org_suppliers', 'org_supplier_*',
            'agents', 'agent_*', 'org_agents', 'org_partners', 'partner_shopping_list_items',
            'purchase_order*', 'stock_deliver*', 'aspo_deposits', 'deposit_request*', 'competitor*',
        ],

        'production' => [
            'productions', 'job_orders', 'job_order_*', 'manufacture_*', 'raw_materials', 'raw_material_stats', 'recipe_step_raw_materials',
            'artisan_assignments',
        ],

        'marketing' => [
            'mailshot*', 'outbox*', 'email_*', 'emails', 'dispatched_emails', 'ses_*', 'sender_emails', '*_has_dispatched_emails', '*_email_recipient*',
            'model_has_dispatched_emails', 'meta_tracking_events', 'whatsapp_*', 'traffic_source*', 'model_has_traffic_sources', 'seo_*', 'search_console_*', 'crux_records',
            'crawl*', 'google_ads_media_assets', 'websites', 'website_*', 'webpages', 'webpage_*', 'web_block*', 'web_layout_templates',
            'model_has_web_blocks', 'model_has_contents', 'banners', 'slides', 'snapshots', 'redirects', 'external_links', 'announcement*',
            'marketing_*', 'customer_interests', 'customer_web_activities', 'customer_product_suggestions', 'ip_geolocations', 'search_logs',
        ],

        'finance' => [
            'payments', 'model_has_payments', 'payment_*', 'org_payment_service_provider*', 'credit_transactions', 'top_ups', 'top_up_*',
            'order_payment_api_points', 'adjustments', 'intrastat_*', '*_accounting_stats', 'invoice_transaction_date_repairs',
        ],

        'hr' => [
            'employees', 'employee_*', 'clockings', 'clocking_machine*', 'timesheets', 'time_trackers', 'attendance_adjustments',
            'leaves', 'leave_*', 'holiday*', 'overtime_*', 'work_schedule*', 'job_information', 'hr_announcements', 'guests', 'qr_scan_logs',
            'restricted_*', 'guest_*', '*_human_resources_stats',
        ],

        /*
         * Engineers only: logs, users, permissions, infrastructure.
         */
        'engineering' => [
            'users', 'user_*', 'admins', 'roles', 'role_*', 'permissions', 'model_has_permissions', 'model_has_roles', 'job_position_role',
            'audits', 'failed_jobs', 'job_exceptions', 'job_statistics', 'queue_statistics', 'queries', 'query_has_models', 'scheduled_task_logs',
            'deployments', 'app_deployments', 'ci_runs', 'committers', 'servers', 'server_metric*', 'debug_webhooks', 'payment_gateway_logs',
            'fetch*', 'chunks', 'uploads', 'upload_records', 'mcp_*', 'ai_usages', 'notifications', 'retina_api_requests', 'aiku_*',
            'staff_conversations', 'staff_conversation_participants',
            'model_has_attachments', 'external_subscriber_*', 'web_vital_samples', 'restricted_country_region_logs', 'subscription_events',
        ],
    ],

    /*
     * Job position codes => tiers. `base` is implied for every MCP SQL user. Engineering comes
     * with the group engineer and QA positions.
     */
    'positions' => [
        'group-admin'    => ['sales', 'customers', 'feedback', 'warehouse', 'procurement', 'production', 'marketing', 'finance', 'hr'],
        'sys-admin'      => ['sales', 'customers', 'feedback', 'warehouse', 'procurement', 'production', 'marketing', 'finance', 'hr'],
        'gp-hd'          => ['sales', 'customers', 'feedback', 'warehouse', 'procurement', 'production', 'marketing', 'finance', 'hr', 'engineering'],
        'gp-hd-m'        => ['sales', 'customers', 'feedback', 'warehouse', 'procurement', 'production', 'marketing', 'finance', 'hr', 'engineering'],
        'gp-qa'          => ['sales', 'customers', 'feedback', 'warehouse', 'procurement', 'finance', 'marketing', 'engineering'],
        'gp-mas'         => ['sales', 'marketing', 'customers', 'feedback', 'production'],
        'gp-wm'          => ['sales', 'marketing', 'customers', 'feedback', 'production'],
        'gp-md'          => ['sales', 'marketing', 'customers', 'feedback', 'production'],
        'gp-vw'          => ['sales', 'marketing', 'customers', 'feedback', 'production'],
        'gp-sc'          => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'gp-sc-w'        => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'gp-g'           => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'gp-clk'         => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'gp-cpl-m'       => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'gp-cpl-s'       => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'gp-cpl-w'       => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'org-admin'      => ['sales', 'customers', 'warehouse', 'procurement', 'production', 'marketing', 'finance', 'feedback'],
        'shop-admin'     => ['sales', 'customers', 'marketing', 'feedback'],
        'acc-m'          => ['finance', 'sales', 'customers', 'feedback', 'hr', 'procurement', 'production', 'warehouse', 'payroll', 'marketing'],
        'acc-c'          => ['finance', 'sales', 'customers', 'feedback', 'hr', 'procurement', 'production', 'warehouse', 'payroll', 'marketing'],
        'acc-o'          => ['finance', 'sales', 'customers', 'feedback', 'hr', 'procurement', 'production', 'warehouse', 'payroll', 'marketing'],
        'acc-v'          => ['finance', 'sales', 'customers', 'feedback', 'hr', 'procurement', 'production', 'warehouse', 'payroll', 'marketing'],
        'buy'            => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'buy-v'          => ['procurement', 'warehouse', 'sales', 'customers', 'marketing', 'production', 'feedback'],
        'agt-m'          => ['procurement', 'warehouse', 'sales', 'feedback'],
        'agt-c'          => ['procurement', 'warehouse', 'sales', 'feedback'],
        'cus-m'          => ['sales', 'customers', 'warehouse', 'procurement', 'finance', 'marketing', 'feedback'],
        'cus-c'          => ['sales', 'customers', 'warehouse', 'procurement', 'finance', 'marketing', 'feedback'],
        'cus-call'       => ['sales', 'customers', 'warehouse', 'procurement', 'finance', 'marketing', 'feedback'],
        'cus-v'          => ['sales'],
        'shk-m'          => ['sales', 'marketing', 'customers', 'feedback', 'production'],
        'shk-c'          => ['sales', 'marketing', 'customers', 'feedback', 'production'],
        'mrk-m'          => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'mrk-c'          => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'ppc-shop'       => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'ppc-m'          => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'ppc-c'          => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'seo-m'          => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'seo-c'          => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'social-m'       => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'social-c'       => ['sales', 'customers', 'marketing', 'procurement', 'production', 'feedback'],
        'dist-m'         => ['sales', 'warehouse', 'production'],
        'dist-v'         => ['warehouse', 'sales', 'production'],
        'dist-excp-pick' => ['warehouse', 'sales', 'production'],
        'dist-pik'       => ['warehouse', 'sales', 'production'],
        'dist-pak'       => ['warehouse', 'sales', 'production'],
        'wah-m'          => ['warehouse', 'procurement', 'sales', 'production'],
        'wah-sc'         => ['warehouse', 'sales', 'production'],
        'wah-v'          => ['warehouse', 'sales', 'production'],
        'gi-m'           => ['warehouse', 'procurement', 'sales', 'production'],
        'gi-c'           => ['warehouse', 'sales', 'production'],
        'gi-v'           => ['warehouse', 'sales', 'production'],
        'ful-m'          => ['sales', 'customers', 'warehouse', 'feedback'],
        'ful-c'          => ['sales', 'customers', 'feedback'],
        'ful-wc'         => ['warehouse', 'sales', 'production'],
        'ful-v'          => ['warehouse', 'sales', 'production'],
        'prod-m'         => ['production', 'sales', 'procurement', 'warehouse', 'marketing', 'feedback', 'payroll'],
        'prod-d'         => ['production', 'sales', 'procurement', 'warehouse', 'marketing', 'feedback'],
        'prod-c'         => ['production', 'sales', 'procurement', 'warehouse', 'marketing', 'feedback'],
        'prod-p'         => ['production', 'sales', 'procurement', 'warehouse', 'marketing', 'feedback'],
        'prod-v'         => ['production', 'sales', 'procurement', 'warehouse', 'marketing', 'feedback'],
        'hr-m'           => ['hr', 'feedback', 'payroll', 'hr_records', 'production'],
        'hr-c'           => ['hr', 'feedback', 'payroll', 'hr_records', 'production'],
        'hr-v'           => ['hr', 'feedback', 'payroll', 'hr_records', 'production'],
    ],
];
