<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\AsJob;
use Lorisleiva\Actions\Concerns\AsObject;

class LogPublicVisit
{
    use AsController;
    use AsJob;
    use AsObject;

    public string $jobQueue = 'default';

    public function asController(Request $request): Response
    {
        $this->handle($request, (string) $request->query('p', ''), (string) $request->query('r', ''));

        return response()->noContent();
    }

    public function handle(Request $request, string $path, string $referrerUrl): void
    {
        $userAgent = (string) $request->userAgent();

        if ($path !== '' && mb_strlen($path) <= 255 && str_starts_with($path, '/')) {
            $referrer = parse_url($referrerUrl, PHP_URL_HOST);

            self::dispatch([
                'created_at'   => now(),
                'path'         => $path,
                'referrer'     => $referrer && $referrer !== config('app.domain') ? mb_substr($referrer, 0, 255) : null,
                'country'      => mb_substr((string) $request->header('CF-IPCountry'), 0, 2) ?: null,
                'visitor_hash' => mb_substr(hash('sha256', $request->ip().'|'.$userAgent), 0, 16),
                'is_bot'       => $userAgent === '' || (new CrawlerDetect())->isCrawler($userAgent) || (bool) preg_match('/headless|preview|scrape/i', $userAgent),
                'user_agent'   => mb_substr($userAgent, 0, 255),
            ]);
        }
    }

    /** @param array{created_at: \Illuminate\Support\Carbon, path: string, referrer: ?string, country: ?string, visitor_hash: string, is_bot: bool, user_agent: string} $visit */
    public function asJob(array $visit): void
    {
        DB::table('aiku_public_visits')->insert($visit);
    }
}
