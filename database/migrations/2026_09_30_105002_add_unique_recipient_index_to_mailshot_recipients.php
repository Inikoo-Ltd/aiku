<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 10:50:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    protected string $index = 'mailshot_recipients_mailshot_id_recipient_type_recipient_id_uni';

    /**
     * A retried send chunk must not be able to store the same person twice for one mailshot, because
     * every stored recipient row is an email that goes out. The existing (recipient_type, recipient_id,
     * mailshot_id) index stays: it answers the per-customer lookups this one cannot.
     */
    public function up(): void
    {
        $isPostgres   = DB::getDriverName() === 'pgsql';
        $concurrently = $isPostgres ? 'CONCURRENTLY ' : '';

        if ($isPostgres) {
            $invalid = DB::selectOne(
                'select 1 as found from pg_index i join pg_class c on c.oid = i.indexrelid where c.relname = ? and not i.indisvalid',
                [$this->index]
            );

            if ($invalid) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$this->index}");
            }
        }

        DB::statement("CREATE UNIQUE INDEX {$concurrently}IF NOT EXISTS {$this->index} ON mailshot_recipients (mailshot_id, recipient_type, recipient_id)");
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        DB::statement("DROP INDEX {$concurrently}IF EXISTS {$this->index}");
    }
};
