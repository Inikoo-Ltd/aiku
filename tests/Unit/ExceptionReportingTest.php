<?php

use App\Exceptions\IrisWebsiteNotFound;
use Illuminate\Contracts\Debug\ExceptionHandler;

test('an unknown storefront domain is answered with a 404 and not reported', function () {
    expect(app(ExceptionHandler::class)->shouldReport(IrisWebsiteNotFound::make()))->toBeFalse()
        ->and(app(ExceptionHandler::class)->shouldReport(new RuntimeException('real')))->toBeTrue();
});
