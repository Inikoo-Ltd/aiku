<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use Illuminate\Support\Facades\DB;

/**
 * A "something changed, refetch" event saved from a model hook fires once per row, so releasing
 * 200 lines in one transaction told every open screen to refetch 200 times. Inside a transaction
 * this sends it once, after the commit; outside one it is sent at once, as before.
 */
trait DispatchesOncePerTransaction
{
    /** @var array<string, true> */
    private static array $pendingOncePerTransaction = [];

    public static function dispatchOncePerTransaction(mixed ...$arguments): void
    {
        if (DB::transactionLevel() === 0) {
            self::$pendingOncePerTransaction = [];
            rescue(fn () => static::dispatch(...$arguments));

            return;
        }

        $key = json_encode($arguments);
        if (isset(self::$pendingOncePerTransaction[$key])) {
            return;
        }

        self::$pendingOncePerTransaction[$key] = true;

        DB::afterRollBack(function () use ($key) {
            unset(self::$pendingOncePerTransaction[$key]);
        });
        DB::afterCommit(function () use ($key, $arguments) {
            unset(self::$pendingOncePerTransaction[$key]);
            rescue(fn () => static::dispatch(...$arguments));
        });
    }
}
