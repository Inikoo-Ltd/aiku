<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection\Wise;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

trait WithWiseApi
{
    protected function wiseGet(#[\SensitiveParameter] string $token, string $path, array $query = []): ?array
    {
        return Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['Accept-Language' => 'en'])
            ->timeout(30)
            ->retry(
                4,
                fn (int $attempt) => $attempt * 2000,
                fn (\Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false
            )
            ->get('https://api.wise.com'.$path, $query)
            ->throw()
            ->json();
    }
}
