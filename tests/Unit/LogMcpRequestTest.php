<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\LogMcpRequest;

function mcpResponseError(string $content): ?string
{
    $method = new ReflectionMethod(LogMcpRequest::class, 'responseError');

    return $method->invoke(new LogMcpRequest(), $content);
}

test('tool-level error is flagged', function () {
    expect(mcpResponseError('{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"boom"}],"isError":true}}'))->toBe('boom');
});

test('protocol error such as unknown tool is flagged', function () {
    expect(mcpResponseError('{"jsonrpc":"2.0","id":1,"error":{"code":-32602,"message":"Tool not found"}}'))->toBe('Tool not found');
});

test('successful response with escaped error text in payload is not flagged', function () {
    expect(mcpResponseError('{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"{\"orders\":3,\"last_error\":{\"note\":\"none\"}}"}],"isError":false}}'))->toBeNull();
});
