<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-13h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Response;

class SetMobileApiLocale
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $supportedLocales = null;

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::requestedLocale($request->headers->get('Accept-Language'));
        if ($locale === null) {
            return $next($request);
        }

        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }

    public static function requestedLocale(?string $acceptLanguage): ?string
    {
        if ($acceptLanguage === null || trim($acceptLanguage) === '') {
            return null;
        }

        $supportedLocales = self::supportedLocales();

        foreach (AcceptHeader::fromString($acceptLanguage)->all() as $item) {
            if ($item->getQuality() <= 0) {
                continue;
            }

            $tag = strtolower(str_replace('_', '-', trim($item->getValue())));
            while ($tag !== '') {
                if (isset($supportedLocales[$tag])) {
                    return $supportedLocales[$tag];
                }

                $lastSeparator = strrpos($tag, '-');
                $tag           = $lastSeparator === false ? '' : substr($tag, 0, $lastSeparator);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function supportedLocales(): array
    {
        return self::$supportedLocales ??= collect(glob(lang_path('*.json')) ?: [])
            ->map(fn (string $path) => basename($path, '.json'))
            ->mapWithKeys(fn (string $locale) => [strtolower($locale) => $locale])
            ->all();
    }
}
