<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Servers;

use App\Mcp\Tools\ShopSalesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Aiku')]
#[Version('1.0.0')]
#[Instructions('Read-only access to Aiku commerce data. Every tool is scoped by the authenticated user\'s permissions: a tool call against a shop the user cannot view returns a permission error.')]
class AikuServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ShopSalesTool::class,
    ];
}
