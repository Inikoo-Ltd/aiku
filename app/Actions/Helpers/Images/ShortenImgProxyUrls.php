<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 00:59:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ShortenImgProxyUrls
{
    use AsAction;

    private const string BASE62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * @param  array<int, string>  $urls
     *
     * @return array<string, string> long url => short url; a url that cannot be shortened maps to itself
     */
    public function handle(array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return [];
        }

        try {
            $codes = [];
            foreach ($urls as $url) {
                $codes[$url] = self::code($url, 0);
            }

            $taken = DB::table('image_short_urls')->whereIn('code', array_values($codes))->pluck('url', 'code');

            $toInsert = [];
            $slow     = [];
            foreach ($codes as $url => $code) {
                if (!$taken->has($code)) {
                    $toInsert[$url] = $code;
                } elseif ($taken[$code] !== $url) {
                    $slow[] = $url;
                }
            }

            if ($toInsert !== []) {
                $inserted = DB::table('image_short_urls')->insertOrIgnore(
                    array_map(fn ($url, $code) => ['code' => $code, 'url' => $url], array_keys($toInsert), $toInsert)
                );
                if ($inserted !== count($toInsert)) {
                    $slow = array_merge($slow, array_keys($toInsert));
                }
            }

            foreach ($slow as $url) {
                $codes[$url] = $this->claimCode($url);
            }

            $shortUrls = [];
            foreach ($codes as $url => $code) {
                $shortUrls[$url] = self::shortUrl($code, $url);
            }

            return $shortUrls;
        } catch (Throwable $e) {
            report($e);

            return array_combine($urls, $urls);
        }
    }

    private function claimCode(string $url): string
    {
        for ($attempt = 0; ; $attempt++) {
            $code = self::code($url, $attempt);
            DB::table('image_short_urls')->insertOrIgnore(['code' => $code, 'url' => $url]);
            if (DB::table('image_short_urls')->where('code', $code)->value('url') === $url) {
                return $code;
            }
        }
    }

    public static function code(string $url, int $attempt): string
    {
        $number = hexdec(substr(hash('xxh3', $attempt ? $url.'#'.$attempt : $url), 0, 15));

        $code = '';
        do {
            $code   = self::BASE62[$number % 62].$code;
            $number = intdiv($number, 62);
        } while ($number > 0);

        return $code;
    }

    private static function shortUrl(string $code, string $longUrl): string
    {
        $extension = Str::of(parse_url($longUrl, PHP_URL_PATH) ?? '')->afterLast('/')->match('/\.([a-z0-9]{2,4})$/')->value();

        return route('image_short_url', ['code' => $extension ? "$code.$extension" : $code]);
    }
}
