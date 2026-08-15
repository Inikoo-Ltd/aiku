<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * These tables reference dispatched_emails with ON DELETE CASCADE but never had an index on the
     * referencing column, so Postgres answers each cascade with a sequential scan, once per deleted
     * row. Harmless while nothing deletes dispatched emails; fatal for the retention archiver, which
     * deletes them in batches of thousands.
     */
    protected array $indexes = [
        'mailshot_has_dispatched_emails_dispatched_email_id_index'      => 'mailshot_has_dispatched_emails',
        'customer_has_dispatched_emails_dispatched_email_id_index'      => 'customer_has_dispatched_emails',
        'prospect_has_dispatched_emails_dispatched_email_id_index'      => 'prospect_has_dispatched_emails',
        'email_ongoing_run_has_disp_emails_dispatched_email_id_index'   => 'email_ongoing_run_has_dispatched_emails',
        'email_bulk_run_has_disp_emails_dispatched_email_id_index'      => 'email_bulk_run_has_dispatched_emails',
        'user_has_dispatched_emails_dispatched_email_id_index'          => 'user_has_dispatched_emails',
        'ext_subscriber_email_recipient_has_disp_emails_disp_id_index'  => 'external_subscriber_email_recipient_has_dispatched_emails',
        'web_user_has_dispatched_emails_dispatched_email_id_index'      => 'web_user_has_dispatched_emails',
        'test_email_recipient_has_disp_emails_dispatched_email_id_index' => 'test_email_recipient_has_dispatched_emails',
        'chat_email_recipient_has_disp_emails_dispatched_email_id_index' => 'chat_email_recipient_has_dispatched_emails',
    ];

    public function up(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        foreach ($this->indexes as $name => $table) {
            DB::statement("CREATE INDEX {$concurrently}IF NOT EXISTS {$name} ON {$table} (dispatched_email_id)");
        }
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        foreach (array_keys($this->indexes) as $name) {
            DB::statement("DROP INDEX {$concurrently}IF EXISTS {$name}");
        }
    }
};
