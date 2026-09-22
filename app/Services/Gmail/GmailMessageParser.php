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
            return self::trimQuotedHistory(self::htmlToText(self::decode($html)));
        }

        return '';
    }

    /**
     * The message as it was designed, for showing. Null when the sender only wrote plain text,
     * which is most real correspondence: there is nothing to render and the text is the message.
     */
    public static function htmlBody(array $raw): ?string
    {
        $html = self::findPart(Arr::get($raw, 'payload', []), 'text/html');

        return $html !== null ? self::decode($html) : null;
    }

    /**
     * strip_tags removes the tags but keeps what is between them, so a stylesheet in the head
     * survived as text and the message read "*{box-sizing:border-box}body{margin:0...". Those
     * blocks go whole, before anything else is stripped.
     */
    private static function htmlToText(string $html): string
    {
        $html = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>'], "\n", $html);

        // Decode before stripping, and do both twice: "&lt;script&gt;" survives a strip
        // untouched, so decoding afterwards would write a real tag into the stored message.
        $text = $html;

        for ($pass = 0; $pass < 2; $pass++) {
            $text = preg_replace('/<(style|script|head|title)\b[^>]*>.*?<\/\1>/is', ' ', $text) ?? $text;
            $text = preg_replace('/<!--.*?-->/s', ' ', $text) ?? $text;
            $text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // Layout markup leaves runs of blank lines and non breaking spaces behind it.
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Inline parts are reported too, marked as such: they are the pictures inside an email, and
     * with the markup discarded they are the only way to see what was sent. The caller decides
     * what to do with them, because most are signature logos and spacers.
     *
     * @return array<int, array{filename: string, mimeType: string, attachmentId: ?string, data: ?string, inline: bool, contentId: ?string, size: int}>
     */
    public static function attachments(array $part): array
    {
        $attachments = [];
        $filename    = (string) Arr::get($part, 'filename', '');
        $inline      = self::isInline($part);
        $mimeType    = (string) Arr::get($part, 'mimeType', 'application/octet-stream');

        if ($filename !== '' || ($inline && str_starts_with($mimeType, 'image/'))) {
            $attachments[] = [
                'filename'     => $filename !== '' ? $filename : 'image',
                'mimeType'     => $mimeType,
                'attachmentId' => Arr::get($part, 'body.attachmentId'),
                'data'         => Arr::get($part, 'body.data'),
                'inline'       => $inline,
                'contentId'    => self::contentId($part),
                'size'         => (int) Arr::get($part, 'body.size', 0),
            ];
        }

        foreach (Arr::get($part, 'parts', []) as $child) {
            array_push($attachments, ...self::attachments($child));
        }

        return $attachments;
    }

    /**
     * Photographs too large to attach are sent as Drive links, and Gmail writes them into the
     * text as "[image: Image]" with a filename and nothing behind it. The links are only in the
     * markup, so that is where they are read from.
     *
     * @return array<int, string>
     */
    public static function driveFileIds(?string $html): array
    {
        if (! $html) {
            return [];
        }

        preg_match_all('#drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:[^"\'<>]*&)?id=)([A-Za-z0-9_-]{10,})#i', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function decodeData(string $base64url): string
    {
        return self::decode($base64url);
    }

    /**
     * What the markup points at with src="cid:...". The header carries it in angle brackets,
     * the markup never does.
     */
    private static function contentId(array $part): ?string
    {
        $contentId = self::header(['payload' => $part], 'Content-ID');

        if ($contentId === null) {
            return null;
        }

        return trim(trim($contentId), '<>') ?: null;
    }

    private static function isInline(array $part): bool
    {
        $disposition = self::header(['payload' => $part], 'Content-Disposition');

        return $disposition !== null && str_starts_with(strtolower(trim($disposition)), 'inline');
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
