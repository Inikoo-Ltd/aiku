<?php

use App\Services\SearchConsole\SearchConsoleClient;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

test('search console retries a quota burst and a dropped connection, not a refusal', function () {
    $googleError = fn (int $code, string $reason) => new GoogleServiceException('{}', $code, null, [['reason' => $reason]]);

    expect(SearchConsoleClient::isWorthRetrying($googleError(403, 'quotaExceeded')))->toBeTrue()
        ->and(SearchConsoleClient::isWorthRetrying($googleError(429, 'anything')))->toBeTrue()
        ->and(SearchConsoleClient::isWorthRetrying(new ConnectException('reset', new Request('POST', 'https://google.test'))))->toBeTrue()
        ->and(SearchConsoleClient::isQuotaError($googleError(403, 'quotaExceeded')))->toBeTrue()
        ->and(SearchConsoleClient::isQuotaError(new ConnectException('reset', new Request('POST', 'https://google.test'))))->toBeFalse()
        ->and(SearchConsoleClient::isWorthRetrying($googleError(403, 'forbidden')))->toBeFalse()
        ->and(SearchConsoleClient::isWorthRetrying(new RuntimeException('boom')))->toBeFalse();
});
