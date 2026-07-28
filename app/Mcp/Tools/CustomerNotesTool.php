<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerNote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Notes recorded against a customer, newest first, optionally filtered by search text.')]
#[IsReadOnly]
class CustomerNotesTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::CRM_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'     => ['required', 'string'],
            'customer' => ['required', 'string'],
            'search'   => ['sometimes', 'string'],
            'limit'    => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('No shop matching that name is available to you. Shops are identified by a short slug or code (for example eu, aroma, uk) — not by their full display name. Call my-access-tool to list the shops you can query, and use a value from there. Do not guess.');
        }

        $customer = Customer::where('shop_id', $shop->id)
            ->where('slug', $request->string('customer'))
            ->first();

        if (!$customer) {
            return Response::error('Customer not found.');
        }

        $query = CustomerNote::where('customer_id', $customer->id)
            ->where('event', 'customer_note');

        if ($request->filled('search')) {
            $query->whereRaw('(new_values->>\'note\') COLLATE "C" ILIKE ?', ['%'.$request->string('search').'%']);
        }

        $notes = $query
            ->latest('id')
            ->limit($request->integer('limit', 10))
            ->get(['new_values', 'tags', 'created_at'])
            ->map(fn (CustomerNote $note) => [
                'note' => data_get($note->new_values, 'note'),
                'tags' => $note->tags,
                'date' => $note->created_at?->toDateString(),
            ]);

        return Response::json([
            'shop'     => $shop->name,
            'customer' => $customer->name,
            'notes'    => $notes,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'     => $schema->string()->description('Shop slug or code, e.g. eu or EU')->required(),
            'customer' => $schema->string()->description('Customer slug')->required(),
            'search'   => $schema->string()->description('Optional text to search within notes'),
            'limit'    => $schema->integer()->description('Maximum notes to return, default 10')->min(1)->max(50),
        ];
    }
}
