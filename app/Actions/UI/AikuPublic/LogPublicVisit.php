<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsController;

class LogPublicVisit
{
    use AsController;

    public function asController(Request $request): Response
    {
        $userAgent = (string) $request->userAgent();
        $path = (string) $request->query('p', '');

        if ($path !== '' && mb_strlen($path) <= 255 && str_starts_with($path, '/') && !preg_match('/bot|crawl|spider|slurp|headless|preview|scrape/i', $userAgent)) {
            $referrer = parse_url((string) $request->query('r', ''), PHP_URL_HOST);

            DB::table('aiku_public_visits')->insert([
                'created_at'   => now(),
                'path'         => $path,
                'referrer'     => $referrer && $referrer !== config('app.domain') ? mb_substr($referrer, 0, 255) : null,
                'country'      => mb_substr((string) $request->header('CF-IPCountry'), 0, 2) ?: null,
                'visitor_hash' => mb_substr(hash('sha256', $request->ip().'|'.$userAgent), 0, 16),
            ]);
        }

        return response()->noContent();
    }
}
