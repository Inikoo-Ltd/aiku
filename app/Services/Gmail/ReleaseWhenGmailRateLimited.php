<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\Gmail;

use Illuminate\Http\Client\RequestException;

/**
 * Job middleware: a Gmail call refused for going too fast puts the job back on the queue for a
 * minute or two instead of failing it. Any other failure is thrown as before.
 */
class ReleaseWhenGmailRateLimited
{
    public function handle(object $job, callable $next): void
    {
        try {
            $next($job);
        } catch (RequestException $exception) {
            if (! GmailClient::isRateLimited($exception->response)) {
                throw $exception;
            }

            $job->release(random_int(60, 120));
        }
    }
}
