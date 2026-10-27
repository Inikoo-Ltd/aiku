<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Labels for the goods of a purchase order, for the supplier to stick on: carton, SKO and/or unit labels, one label per carton, per SKO (pack) or per unit of every line, each on its own label-sized page in one PDF of at most 2000 labels (split bigger orders by codes or levels), in the same format the warehouse prints. Returns a link to the PDF that anyone holding it can open for 7 days without an account: put it in the email to the supplier (or download it to attach), after the user confirmed the recipient. Lines that cannot carry a label are listed in skipped with the reason. Needs procurement view in the organisation.')]
#[IsReadOnly]
class PurchaseOrderLabelsTool extends Tool
{
    use WithMcpPermissions;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'   => ['required', 'string'],
            'purchase_order' => ['required', 'string'],
            'levels'         => ['sometimes', 'array', 'min:1'],
            'levels.*'       => ['string', 'in:'.implode(',', PdfPurchaseOrderLabels::LEVELS)],
            'codes'          => ['sometimes', 'array'],
            'codes.*'        => ['string'],
            'sizes'          => ['sometimes', 'array'],
            'sizes.carton'   => ['sometimes', 'string', 'in:'.implode(',', PdfOrgStockLabel::LEVEL_SIZES['carton'])],
            'sizes.sko'      => ['sometimes', 'string', 'in:'.implode(',', PdfOrgStockLabel::LEVEL_SIZES['sko'])],
            'sizes.unit'     => ['sometimes', 'string', 'in:'.implode(',', PdfOrgStockLabel::LEVEL_SIZES['unit'])],
            'standard'       => ['sometimes', 'string', 'in:localised,unified'],
            'batch'          => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();
        if (!$organisation || !$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_VIEW->value, $organisation))) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $reference     = strtolower((string) $request->string('purchase_order'));
        $purchaseOrder = PurchaseOrder::where('organisation_id', $organisation->id)
            ->where(fn ($query) => $query->whereRaw('lower(reference) = ?', [$reference])->orWhereRaw('lower(slug) = ?', [$reference]))
            ->first();
        if (!$purchaseOrder) {
            return Response::error("{$organisation->code} has no purchase order {$request->string('purchase_order')}. Use its reference.");
        }

        $levels   = array_values(array_unique($request->get('levels', ['carton'])));
        $codes    = array_values($request->get('codes', []));
        $sizes    = array_filter($request->get('sizes', []));
        $standard = $request->get('standard', 'localised');
        $batch    = $request->get('batch');

        $plan = PdfPurchaseOrderLabels::make()->plan($purchaseOrder, $levels, $codes, $standard, $batch);
        if (!$plan) {
            return Response::error("No line of {$purchaseOrder->reference} matches ".($codes ? implode(', ', $codes) : 'anything').'.');
        }

        $printable = array_filter($plan, fn (array $item) => !$item['skipped'] && $item['labels'] > 0);
        $skipped   = array_filter($plan, fn (array $item) => (bool) $item['skipped']);
        if (!$printable) {
            return Response::error('None of these labels can be printed: '.implode('; ', array_map(fn (array $item) => "{$item['code']} {$item['level']}: {$item['skipped']}", $skipped)));
        }

        $pages = array_sum(array_column($printable, 'labels'));
        if ($pages > PdfPurchaseOrderLabels::MAX_LABELS) {
            return Response::error("That is {$pages} labels and one file holds at most ".PdfPurchaseOrderLabels::MAX_LABELS.'. Split it into several calls with fewer codes or levels: '.implode(', ', array_map(fn (array $item) => "{$item['code']} {$item['level']} {$item['labels']}", $printable)));
        }

        return Response::json([
            'purchase_order' => $purchaseOrder->reference,
            'supplier'       => $purchaseOrder->parent_name,
            'filename'       => PdfPurchaseOrderLabels::make()->filename($purchaseOrder),
            'url'            => PdfPurchaseOrderLabels::make()->url($purchaseOrder, $levels, $codes, $sizes, $standard, $batch),
            'expires_at'     => now()->addDays(PdfPurchaseOrderLabels::LINK_DAYS)->toDateString(),
            'pages'          => $pages,
            'labels'         => array_values(array_map(fn (array $item) => [
                'code'   => $item['code'],
                'level'  => $item['level'],
                'units'  => (float) $item['line']->quantity_ordered,
                'labels' => $item['labels'],
            ], $printable)),
            'skipped'        => array_values(array_map(fn (array $item) => ['code' => $item['code'], 'level' => $item['level'], 'reason' => $item['skipped']], $skipped)),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation'   => $schema->string()->description('Slug or code of the buying organisation')->required(),
            'purchase_order' => $schema->string()->description('Purchase order reference, e.g. AEx003-SK')->required(),
            'levels'         => $schema->array()->items($schema->string()->enum(PdfPurchaseOrderLabels::LEVELS))->description('Which labels: carton (one per carton), sko (one per pack), unit (one per unit), any combination. Default carton'),
            'codes'          => $schema->array()->items($schema->string())->description('Only these SKO or supplier product codes; default every line'),
            'sizes'          => $schema->object([
                'carton' => $schema->string()->enum(PdfOrgStockLabel::LEVEL_SIZES['carton']),
                'sko'    => $schema->string()->enum(PdfOrgStockLabel::LEVEL_SIZES['sko']),
                'unit'   => $schema->string()->enum(PdfOrgStockLabel::LEVEL_SIZES['unit']),
            ])->description('Label size in mm per level, width x height; default the warehouse default'),
            'standard'       => $schema->string()->enum(['localised', 'unified'])->description('localised (default, what the warehouse and agents print) or unified (GS1, needs a batch for SKO and carton)'),
            'batch'          => $schema->string()->description('Batch code for unified labels, up to 20 of A-Z 0-9 -'),
        ];
    }
}
