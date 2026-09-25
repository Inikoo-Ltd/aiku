<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Inventory\OrgStock\GetOrgStockDiscontinuePreview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Before discontinuing SKOs (organisation stock), shows what still hangs off each one: state in every organisation carrying the stock, stock on hand, days of cover, open purchase orders and pending deliveries with references, active dropship portfolios (customers, per platform), products in external marketplace shops, live web pages, open customer orders, exclusive flag, and the updated_at to pass to org-stock-discontinue-tool. Read only. Only for users enrolled to discontinue SKOs through their assistant.')]
#[IsReadOnly]
class OrgStockDiscontinuePreviewTool extends AikuOrgStockDiscontinueTool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'codes'        => ['required', 'array', 'min:1', 'max:50'],
            'codes.*'      => ['string'],
        ]);

        $denied = $this->denied($request);
        if ($denied) {
            return $denied;
        }

        $organisation = $this->organisationByCode($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        [$orgStocks, $missing] = $this->orgStocksByCodes($organisation, $request->get('codes'));

        return Response::json([
            'organisation' => $organisation->code,
            'not_found'    => $missing,
            'note'         => 'Customer stores are never written to: once discontinued, each customer\'s own stock sync shows zero and they delist it themselves. Mailshots are not linked to products in aiku.',
            'previews'     => GetOrgStockDiscontinuePreview::make()->action($organisation, $orgStocks->pluck('id')->all(), $request->user()),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation code or slug, e.g. aw, es, sk')->required(),
            'codes'        => $schema->array()->items($schema->string())->description('SKO codes in that organisation, up to 50')->required(),
        ];
    }
}
