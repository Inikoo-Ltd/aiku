<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Apr 2026 21:17:08 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Support\NewRelic;

class Agent
{
    public function isLoaded(): bool
    {
        return extension_loaded('newrelic');
    }

    public function startTransaction(string $appName): void
    {
        if (function_exists('newrelic_end_transaction')) {
            call_user_func('newrelic_end_transaction', $appName);
        }
        $this->markAsWebTransaction();
    }

    public function terminateTransaction(): void
    {
        if (function_exists('newrelic_end_transaction')) {
            call_user_func('newrelic_end_transaction');
        }
    }

    public function markAsWebTransaction(): void
    {
        if (function_exists('newrelic_background_job')) {
            call_user_func('newrelic_background_job', false);
        }
    }

    public function nameTransaction(string $transactionName): void
    {
        if (function_exists('newrelic_name_transaction')) {
            call_user_func('newrelic_name_transaction', $transactionName);
        }
    }

    public function addCustomParameter(string $name, string $value): void
    {
        if (function_exists('newrelic_add_custom_parameter')) {
            call_user_func('newrelic_add_custom_parameter', $name, $value);
        }
    }
}
