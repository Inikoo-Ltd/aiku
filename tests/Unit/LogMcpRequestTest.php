<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\LogMcpRequest;

function mcpResponseHasError(string $content): bool
{
    $method = new ReflectionMethod(LogMcpRequest::class, 'responseHasError');

    return $method->invoke(new LogMcpRequest(), $content);
}

test('tool-level error is flagged', function () {
    expect(mcpResponseHasError('{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"boom"}],"isError":true}}'))->toBeTrue();
});

test('protocol error such as unknown tool is flagged', function () {
    expect(mcpResponseHasError('{"jsonrpc":"2.0","id":1,"error":{"code":-32602,"message":"Tool not found"}}'))->toBeTrue();
});

test('successful response with escaped error text in payload is not flagged', function () {
    expect(mcpResponseHasError('{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"{\"orders\":3,\"last_error\":{\"note\":\"none\"}}"}],"isError":false}}'))->toBeFalse();
});
