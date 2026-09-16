<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\Gmail;

use Illuminate\Support\Arr;

class GmailMessageParser
{
    public static function threadId(array $raw): string
    {
        return (string) Arr::get($raw, 'threadId');
    }

    public static function header(array $raw, string $name): ?string
    {
        $headers = Arr::get($raw, 'payload.headers', []);

        foreach ($headers as $header) {
            if (strcasecmp(Arr::get($header, 'name', ''), $name) === 0) {
                return Arr::get($header, 'value');
            }
        }

        return null;
    }

    /**
     * @return array{name: ?string, address: ?string}
     */
    public static function fromAddress(array $raw): array
    {
        $from = self::header($raw, 'From');

        if (! $from) {
            return ['name' => null, 'address' => null];
        }

        if (preg_match('/^(.*?)<(.+?)>$/', trim($from), $matches)) {
            $name = trim($matches[1], " \t\"'");

            return ['name' => $name !== '' ? $name : null, 'address' => trim($matches[2])];
        }

        return ['name' => null, 'address' => trim($from)];
    }

    public static function body(array $raw): string
    {
        $payload = Arr::get($raw, 'payload', []);

        $plain = self::findPart($payload, 'text/plain');
        if ($plain !== null) {
            return self::trimQuotedHistory(self::decode($plain));
        }

        $html = self::findPart($payload, 'text/html');
        if ($html !== null) {
            $html = str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", self::decode($html));

            return self::trimQuotedHistory(trim(strip_tags($html)));
        }

        return '';
    }

    private static function findPart(array $part, string $mimeType): ?string
    {
        if (Arr::get($part, 'mimeType') === $mimeType) {
            $data = Arr::get($part, 'body.data');

            return $data ?: null;
        }

        foreach (Arr::get($part, 'parts', []) as $child) {
            $found = self::findPart($child, $mimeType);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function decode(string $base64url): string
    {
        return (string) base64_decode(str_replace(['-', '_'], ['+', '/'], $base64url));
    }

    // ponytail: quoted-reply trimming is a heuristic (first "On ... wrote:" or leading ">" block), good enough until real threads misbehave
    private static function trimQuotedHistory(string $body): string
    {
        $lines = preg_split('/\R/', $body);
        $cut   = count($lines);

        foreach ($lines as $index => $line) {
            if (preg_match('/^On .+wrote:$/', trim($line)) || str_starts_with(trim($line), '>')) {
                $cut = $index;
                break;
            }
        }

        return trim(implode("\n", array_slice($lines, 0, $cut)));
    }
}
