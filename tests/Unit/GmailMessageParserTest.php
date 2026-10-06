<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Services\Gmail\GmailMessageParser;

function gmailTextPart(string $mimeType, string $bytes, string $charset): array
{
    return [
        'mimeType' => $mimeType,
        'headers'  => [['name' => 'Content-Type', 'value' => "$mimeType; charset=\"$charset\""]],
        'body'     => ['data' => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=')],
    ];
}

test('an outlook body labelled iso-8859-1 that gmail already gave back as utf-8 is not converted twice', function () {
    $raw = ['payload' => [
        'mimeType' => 'multipart/alternative',
        'parts'    => [
            gmailTextPart('text/plain', "Mi nombre es Fátima, ¿podéis ayudarme?", 'iso-8859-1'),
            gmailTextPart('text/html', '<div>Mi nombre es Fátima, ¿podéis ayudarme?</div>', 'iso-8859-1'),
        ],
    ]];

    expect(GmailMessageParser::body($raw))->toBe('Mi nombre es Fátima, ¿podéis ayudarme?')
        ->and(GmailMessageParser::htmlBody($raw))->toBe('<div>Mi nombre es Fátima, ¿podéis ayudarme?</div>');
});

test('a body that really is iso-8859-1 is still converted to utf-8', function () {
    $raw = ['payload' => gmailTextPart('text/plain', mb_convert_encoding('Mi nombre es Fátima', 'ISO-8859-1', 'UTF-8'), 'iso-8859-1')];

    expect(GmailMessageParser::body($raw))->toBe('Mi nombre es Fátima');
});

test('japanese mail in iso-2022-jp is still converted although its bytes look like utf-8', function () {
    $raw = ['payload' => gmailTextPart('text/plain', mb_convert_encoding('日本語のメール', 'ISO-2022-JP', 'UTF-8'), 'ISO-2022-JP')];

    expect(GmailMessageParser::body($raw))->toBe('日本語のメール');
});
