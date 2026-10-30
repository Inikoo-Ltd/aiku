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

test('a line of the customer\'s own ending in a colon right above a quote is kept with what it quotes', function () {
    $body = "Here is my order number:\n> AWS1234";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe($body);
});

test('a quote header straight under the signature, as gmail mobile sends it, is trimmed and the signature kept', function () {
    $body = "Best regards,\n\nHamza\nOn Fri, Sep 25, 2026 at 12:01 PM AW Artisan Europe\nwrote:\n\n> earlier";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe("Best regards,\n\nHamza");
});

test('a chinese quote header with a full-width colon is trimmed', function () {
    $body = "好的\n\nLavera <x@y.com> 于2026年10月29日周四 13:20写道：\n\n> hi";

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/plain', $body, 'utf-8')]))->toBe('好的');
});

test('an html only gmail reply loses the quoted email and its header, in the sender\'s language', function () {
    $html = '<div dir="ltr">Nip:7011323360</div><br><div class="gmail_quote gmail_quote_container"><div dir="ltr" class="gmail_attr">29 Eki 2026 Per 13:20 tarihinde Lavera &lt;<a href="mailto:x@y.com">x@y.com</a>&gt; şunu yazdı:<br></div><blockquote class="gmail_quote" style="margin:0px">Hello can You send Faktura in KSeF?</blockquote></div>';

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/html', $html, 'utf-8')]))->toBe('Nip:7011323360');
});

test('an html only forward keeps the forwarded email, which is not a quote', function () {
    $html = '<div>See below</div><div class="gmail_quote"><div class="gmail_attr">---------- Forwarded message ---------<br>From: DPD</div><div>Parcel 123 is delayed</div></div>';

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/html', $html, 'utf-8')]))->toContain('Parcel 123 is delayed');
});

test('an address in angle brackets is kept in the customer\'s text and markup is still stripped', function () {
    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/html', '<p>Write to Lavera &lt;lavera@example.com&gt; or <b>me</b> &lt;script&gt;x&lt;/script&gt;</p>', 'utf-8')]))
        ->toBe('Write to Lavera <lavera@example.com> or me x')
        ->and(stripTagsKeepingEmailAddresses("Lavera <lavera@example.com> <img src=x onerror=alert(1)>\u{E000}img src=x onerror=alert(1)\u{E001}"))
        ->toBe('Lavera <lavera@example.com> img src=x onerror=alert(1)')
        ->and(stripTagsKeepingEmailAddresses('<a/b@c.d onclick=x>'))->toBe('');
});

test('a reply written below the quote or between quotes is kept, in plain text and html', function (string $mimeType, string $body, string $kept) {
    expect(GmailMessageParser::body(['payload' => gmailTextPart($mimeType, $body, 'utf-8')]))->toContain($kept);
})->with([
    'thunderbird below'      => ['text/html', '<div class="moz-cite-prefix">On 29/10/2026 13:20, Shop wrote:</div><blockquote type="cite" cite="mid:x">Your order</blockquote><p>Thank you, but the parcel arrived damaged</p>', 'arrived damaged'],
    'apple mail between'     => ['text/html', 'Answers inline:<blockquote type="cite">Q1</blockquote>Red<blockquote type="cite">Q2</blockquote>20', 'Red'],
    'gmail quote first'      => ['text/html', '<div class="gmail_quote"><blockquote class="gmail_quote">Can you send it?</blockquote></div><div>Yes, attached. Also change the address</div>', 'change the address'],
    'plain below'            => ['text/plain', "On Thu, Oct 29, 2026 at 1:20 PM Shop <a@b.com> wrote:\n> Your order\n\nPlease send 5 units", 'Please send 5 units'],
]);

test('an apple mail forward, which is in a blockquote, keeps the forwarded email', function () {
    $html = 'FYI<br>Begin forwarded message:<br><blockquote type="cite"><b>From:</b> DPD<br>Parcel 123 is delayed</blockquote>';

    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/html', $html, 'utf-8')]))->toContain('Parcel 123 is delayed');
});

test('a quote inside a comment or a stylesheet does not cut the email', function () {
    expect(GmailMessageParser::body(['payload' => gmailTextPart('text/html', '<!-- <blockquote class="gmail_quote"> -->Hello, order 123 please', 'utf-8')]))->toBe('Hello, order 123 please')
        ->and(GmailMessageParser::body(['payload' => gmailTextPart('text/html', '<style>/* <blockquote type="cite"> */</style>Hello order 123', 'utf-8')]))->toBe('Hello order 123');
});

test('an address with a nul byte or in invalid utf-8 never keeps the nul or any markup', function () {
    expect(stripTagsKeepingEmailAddresses("<a@b\0c> ok"))->toBe(' ok')
        ->and(stripTagsKeepingEmailAddresses("<a@b.c> \xff <img src=x onerror=alert(1)>"))->toBe("<a@b.c> \xff ");
});
