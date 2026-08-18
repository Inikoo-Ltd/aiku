<?php

use App\Transfers\Aurora\History\HistoryValueExtractor;

it('normalizes blank strings to null and trims whitespace', function () {
    expect(HistoryValueExtractor::normalize('  hello  '))->toBe('hello')
        ->and(HistoryValueExtractor::normalize(''))->toBeNull()
        ->and(HistoryValueExtractor::normalize('   '))->toBeNull()
        ->and(HistoryValueExtractor::normalize(null))->toBeNull();
});

it('repairs mojibake by re-decoding invalid utf8 as windows-1252', function () {
    $latin1Name = mb_convert_encoding('Tomáš', 'Windows-1252', 'UTF-8');

    expect(mb_check_encoding($latin1Name, 'UTF-8'))->toBeFalse();

    $fixed = HistoryValueExtractor::fixEncoding($latin1Name);

    expect($fixed)->toBe('Tomáš')
        ->and(mb_check_encoding($fixed, 'UTF-8'))->toBeTrue();
});

it('leaves valid utf8 untouched', function () {
    expect(HistoryValueExtractor::fixEncoding('Tomáš'))->toBe('Tomáš');
});

it('strips the stray xx artifact after closing tags', function () {
    $html = '<div class="field tr"><div>Time:</div><div>Thu 17 Jun 2021</div></div>xx<div>rest</div>';

    expect(HistoryValueExtractor::cleanArtifacts($html))
        ->toContain('</div></div><div>rest</div>')
        ->not->toContain('xx<div>');

    $tableHtml = '<tr><td>Time:</td><td>Now</td></tr>xx<tr><td>User:</td><td>Bob</td></tr>';

    expect(HistoryValueExtractor::cleanArtifacts($tableHtml))->not->toContain('xx<tr>');
});

it('decodes double escaped html entities', function () {
    $doubleEscaped = '&amp;lt;b&amp;gt;bold&amp;lt;/b&amp;gt;';

    expect(HistoryValueExtractor::cleanArtifacts($doubleEscaped))->toBe('<b>bold</b>');
});

it('collapses whitespace runs and trims', function () {
    expect(HistoryValueExtractor::cleanArtifacts("  a   b\n\tc  "))->toBe('a b c');
});

it('extracts era-B legacy table markup', function () {
    $details = '<table><tr><td>Time:</td><td>Thu 17 Jun 2021 09:06:26 UTC</td></tr>'
        .'<tr><td>User:</td><td>Radoslaw Wlodarczyk</td></tr>'
        .'<tr><td>Action:</td><td>Changed</td></tr>'
        .'<tr><td>Old value:</td><td>preosc</td></tr>'
        .'<tr><td>New value:</td><td>Smellacloud 50ml Essential Oils</td></tr></table>';

    expect(HistoryValueExtractor::extractTable($details))->toBe([
        'time'   => 'Thu 17 Jun 2021 09:06:26 UTC',
        'user'   => 'Radoslaw Wlodarczyk',
        'action' => 'Changed',
        'old'    => 'preosc',
        'new'    => 'Smellacloud 50ml Essential Oils',
    ]);
});

it('extracts era-C div table markup with the xx artifact present', function () {
    $details = "<div class=\"table\">\n"
        .'<div class="field tr"><div>Time:</div><div>Thu 17 Jun 2021 09:06:26 UTC</div></div>xx'."\n"
        .'<div class="field tr"><div>User:</div><div>Radoslaw Wlodarczyk</div></div>'."\n"
        .'<div class="field tr"><div>Action:</div><div>Changed</div></div>'."\n"
        .'<div class="field tr"><div>Old value:</div><div>preosc</div></div>'."\n"
        .'<div class="field tr"><div>New value:</div><div>Smellacloud 50ml Essential Oils</div></div>'."\n"
        .'<div class="field tr"><div>Category:</div><div></div></div></div>';

    expect(HistoryValueExtractor::extractTable($details))->toBe([
        'time'   => 'Thu 17 Jun 2021 09:06:26 UTC',
        'user'   => 'Radoslaw Wlodarczyk',
        'action' => 'Changed',
        'old'    => 'preosc',
        'new'    => 'Smellacloud 50ml Essential Oils',
    ]);
});

it('matches localized labels, slovak and polish', function () {
    $slovak = '<div class="table">'
        .'<div class="field tr"><div>Čas:</div><div>Now</div></div>'
        .'<div class="field tr"><div>Užívateľ:</div><div>Jan</div></div>'
        .'<div class="field tr"><div>akcia:</div><div>Changed</div></div>'
        .'<div class="field tr"><div>Stará hodnota:</div><div>1</div></div>'
        .'<div class="field tr"><div>Nová hodnota:</div><div>2</div></div></div>';

    expect(HistoryValueExtractor::extractTable($slovak))->toBe([
        'time'   => 'Now',
        'user'   => 'Jan',
        'action' => 'Changed',
        'old'    => '1',
        'new'    => '2',
    ]);

    $polish = '<div class="table">'
        .'<div class="field tr"><div>Czas:</div><div>Now</div></div>'
        .'<div class="field tr"><div>Użytkownik:</div><div>Ola</div></div>'
        .'<div class="field tr"><div>Akcja:</div><div>Changed</div></div>'
        .'<div class="field tr"><div>Stara wartość:</div><div>1</div></div>'
        .'<div class="field tr"><div>Nowa wartość:</div><div>2</div></div></div>';

    expect(HistoryValueExtractor::extractTable($polish))->toBe([
        'time'   => 'Now',
        'user'   => 'Ola',
        'action' => 'Changed',
        'old'    => '1',
        'new'    => '2',
    ]);
});

it('returns null old value when the row is entirely absent, first time associated', function () {
    $details = '<table><tr><td>Time:</td><td>Now</td></tr>'
        .'<tr><td>User:</td><td>Bob</td></tr>'
        .'<tr><td>Action:</td><td>Associated</td></tr>'
        .'<tr><td>New value:</td><td>Some Value</td></tr></table>';

    $result = HistoryValueExtractor::extractTable($details);

    expect($result['old'])->toBeNull()
        ->and($result['action'])->toBe('Associated')
        ->and($result['new'])->toBe('Some Value');
});

it('parses the oldest era plain sentence, quoted form', function () {
    expect(HistoryValueExtractor::extractPlainSentence('Status changed from "Pending" to "Active"'))
        ->toBe(['old' => 'Pending', 'new' => 'Active']);
});

it('parses the plain sentence arrow form, literal and entity', function () {
    expect(HistoryValueExtractor::extractPlainSentence('Status changed (Pending→Active)'))
        ->toBe(['old' => 'Pending', 'new' => 'Active'])
        ->and(HistoryValueExtractor::extractPlainSentence('Status changed (Pending&rarr;Active)'))
        ->toBe(['old' => 'Pending', 'new' => 'Active']);
});

it('returns null for plain sentence when no pattern matches', function () {
    expect(HistoryValueExtractor::extractPlainSentence('Nothing relevant here'))->toBeNull();
});

it('parses an adr microformat address block', function () {
    $html = '<div class="adr">'
        .'<span class="fn">John Smith</span>'
        .'<span class="org">Acme Ltd</span>'
        .'<span class="street-address">123 Main St</span>'
        .'<span class="extended-address">Suite 4</span>'
        .'<span class="locality">London</span>'
        .'<span class="postal-code">SW1 1AA</span>'
        .'<span class="administrative-area">Greater London</span>'
        .'<span class="country-name">United Kingdom</span>'
        .'</div>';

    expect(HistoryValueExtractor::parseAdrAddress($html))->toBe([
        'recipient'           => 'John Smith',
        'organization'        => 'Acme Ltd',
        'address_line_1'      => '123 Main St',
        'address_line_2'      => 'Suite 4',
        'locality'            => 'London',
        'postal_code'         => 'SW1 1AA',
        'administrative_area' => 'Greater London',
        'country'             => 'United Kingdom',
    ]);
});

it('returns null when no adr block is present', function () {
    expect(HistoryValueExtractor::parseAdrAddress('<div>plain</div>'))->toBeNull();
});

it('redacts credential values but leaves already masked ones alone', function () {
    expect(HistoryValueExtractor::redactCredential('password', 'plaintextsecret'))->toBe('[redacted]')
        ->and(HistoryValueExtractor::redactCredential('pin_code', '1234'))->toBe('[redacted]')
        ->and(HistoryValueExtractor::redactCredential('password_hash', '********'))->toBe('********')
        ->and(HistoryValueExtractor::redactCredential('email', 'someone@example.com'))->toBe('someone@example.com')
        ->and(HistoryValueExtractor::redactCredential('password', null))->toBeNull();
});
