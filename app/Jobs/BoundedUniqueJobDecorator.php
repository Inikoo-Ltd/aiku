<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Jobs;

use Lorisleiva\Actions\Decorators\UniqueJobDecorator;

/**
 * Laravel never expires a unique-job lock when uniqueFor is 0, and only releases it
 * from inside the worker process. A worker killed without unwinding (OOM, kill -9,
 * host restart) therefore blocks that unique id forever and every later dispatch is
 * silently discarded. This bounds every unique action that declares no jobUniqueFor,
 * at the longest the job can possibly run: its own timeout, or the timeout Horizon
 * gives the supervisor serving its queue.
 */
class BoundedUniqueJobDecorator extends UniqueJobDecorator
{
    public const int FALLBACK_JOB_TIMEOUT = 3600;
    public const int LOCK_MARGIN = 600;

    private static ?array $queueTimeouts = null;

    protected function constructed(): void
    {
        parent::constructed();

        if ($this->uniqueFor === 0) {
            $this->uniqueFor = ($this->timeout ?: $this->workerTimeout()) + self::LOCK_MARGIN;
        }
    }

    private function workerTimeout(): int
    {
        if (self::$queueTimeouts === null) {
            self::$queueTimeouts = [];

            foreach (config('horizon.defaults', []) as $supervisor) {
                foreach ((array)($supervisor['queue'] ?? []) as $queue) {
                    self::$queueTimeouts[$queue] = (int)($supervisor['timeout'] ?? self::FALLBACK_JOB_TIMEOUT);
                }
            }
        }

        return self::$queueTimeouts[$this->queue ?: 'default'] ?? self::FALLBACK_JOB_TIMEOUT;
    }
}
