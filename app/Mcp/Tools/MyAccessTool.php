<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists the shops, organisations and warehouses this user can ask about, with the exact short name (slug) each other tool expects. Call this first when a question names a shop, organisation or warehouse in words, instead of guessing the slug.')]
#[IsReadOnly]
class MyAccessTool extends Tool
{
    use WithMcpPermissions;

    /**
     * Users with direct SQL access work against the database instead: hiding the
     * purpose-built tools from them keeps the choice on our side rather than
     * relying on the assistant to pick correctly.
     */
    public function shouldRegister(Request $request): bool
    {
        return !$request->user()?->can_use_mcp_sql;
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (!$user) {
            return Response::error('Not authenticated.');
        }

        return Response::json([
            'user'          => $user->contact_name ?? $user->username,
            'shops'         => $this->accessibleShops($request),
            'organisations' => $this->accessibleOrganisations($request),
            'warehouses'    => $this->accessibleWarehouses($request),
            'recently_queried' => [
                'shops'         => $this->recentlyQueried($request, 'shop'),
                'organisations' => $this->recentlyQueried($request, 'organisation'),
                'warehouses'    => $this->recentlyQueried($request, 'warehouse'),
            ],
            'hint'          => 'Pass the slug, the code or the full name as the shop, organisation or warehouse argument of the other tools; all are accepted and matching is case-insensitive. People usually refer to these by code. An empty list means this user has no access at that level.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
