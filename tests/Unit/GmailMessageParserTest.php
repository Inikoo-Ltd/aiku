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

test('a quote header in the sender\'s own language, wrapped over two lines, is trimmed with the quote', function () {
    $body = "Nip:7011323360\n\n29 Eki 2026 Per 13:20 tarihinde Lavera <lavera@example.com> şunu\nyazdı:\n\n> Hello can You send Faktura in KSeF?\n>";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe('Nip:7011323360');
});

test('the customer\'s own text above a quote is kept when it does not look like a quote header', function () {
    $body = "Please see below.\n\n> earlier message";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe('Please see below.');
});

test('a line of the customer\'s own ending in a colon right above a quote is kept', function () {
    $body = "Here is my order number:\n> AWS1234";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe('Here is my order number:');
});

test('a quote header straight under the signature, as gmail mobile sends it, is trimmed and the signature kept', function () {
    $body = "Best regards,\n\nHamza\nOn Fri, Sep 25, 2026 at 12:01 PM AW Artisan Europe\nwrote:\n\n> earlier";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe("Best regards,\n\nHamza");
});

test('a chinese quote header with a full-width colon is trimmed', function () {
    $body = "好的\n\nLavera <x@y.com> 于2026年10月29日周四 13:20写道：\n\n> hi";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe('好的');
});
