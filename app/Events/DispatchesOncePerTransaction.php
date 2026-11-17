<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * A "something changed, refetch" event saved from a model hook fires once per row, so releasing
 * 200 lines in one transaction told every open screen to refetch 200 times. Inside a transaction
 * this sends it once, after the commit; outside one it is sent at once, as before.
 *
 * What was already sent is remembered on the outermost transaction itself, so every new
 * transaction starts clean, even after a COMMIT that failed without telling anyone.
 */
trait DispatchesOncePerTransaction
{
    /** @var WeakMap<DatabaseTransactionRecord, array<string, true>>|null */
    private static ?WeakMap $pendingByTransaction = null;

    public static function dispatchOncePerTransaction(mixed ...$arguments): void
    {
        if (DB::transactionLevel() === 0) {
            rescue(fn () => static::dispatch(...$arguments));

            return;
        }

        $dispatchAfterCommit = fn () => DB::afterCommit(fn () => rescue(fn () => static::dispatch(...$arguments)));

        $transaction = self::outermostTransaction();
        if (!$transaction) {
            $dispatchAfterCommit();

            return;
        }

        self::$pendingByTransaction ??= new WeakMap();
        $key     = json_encode($arguments);
        $pending = self::$pendingByTransaction[$transaction] ?? [];
        if (isset($pending[$key])) {
            return;
        }

        self::$pendingByTransaction[$transaction] = $pending + [$key => true];

        DB::afterRollBack(function () use ($transaction, $key) {
            $pending = self::$pendingByTransaction[$transaction] ?? [];
            unset($pending[$key]);
            self::$pendingByTransaction[$transaction] = $pending;
        });
        $dispatchAfterCommit();
    }

    private static function outermostTransaction(): ?DatabaseTransactionRecord
    {
        $connectionName = DB::connection()->getName();

        return app('db.transactions')->getPendingTransactions()
            ->last(fn (DatabaseTransactionRecord $transaction) => $transaction->connection === $connectionName && $transaction->level === 1);
    }
}
