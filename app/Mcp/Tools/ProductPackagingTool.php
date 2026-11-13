<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Goods\Packaging\RecordMeasuredTradeUnitPackaging;
use App\Actions\Goods\Packaging\SyncPackingDropdownComponents;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

/**
 * Packaging weighed on SKOs at our warehouse, saved as the measured packaging of each SKO's trade unit through
 * RecordMeasuredTradeUnitPackaging. Every SKO with one trade unit can take it, whoever supplied it; the same pieces
 * again leave it unchanged.
 */
#[Description('Records the packaging of SKOs weighed at our warehouse (EPR): it becomes the measured packaging of the SKO\'s trade unit, which the EPR returns read. Each item names a SKO code and its packaging pieces: material (one of: bubble_wrap, air_pillow, epe_foam, eps_foam, shrink_film, stretch_film, pe_bag, opp_bag, zip_bag, tissue_paper, kraft_paper, newspaper, shredded_paper, honeycomb_paper, cardboard_sheet, pulp_tray, pack_box, master_carton, wood_wool, wooden_crate, textile_bag, strapping, plastic_tape, paper_tape, label_insert, silica_gel, other), weight_g of one piece, quantity per SKO (default 1) and an optional description. Map what was measured to the closest material: paper box = pack_box, plastic bag = pe_bag (opp_bag if clear and crisp), label or paper tag = label_insert; anything else is other with a description (rubber band). Send every piece of the SKO at once: the pieces sent replace the trade unit\'s packaging. Manuals, instructions and leaflets are not packaging: leave them out and tell the user they go in Non-packaging materials on the trade unit\'s Edit page. SKOs whose packaging already holds the same pieces are skipped as unchanged. Only call after the user confirmed the SKOs and weights in their own words, passing their request text. Logged in the AI changes log and revertable.')]
class ProductPackagingTool extends AikuOrgStockDiscontinueTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'                => ['required', 'string'],
            'items'                       => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sko'                 => ['required', 'string'],
            'items.*.lines'               => ['required', 'array', 'min:1', 'max:30'],
            'items.*.lines.*.material'    => ['required', Rule::in(array_keys(SyncPackingDropdownComponents::ITEMS))],
            'items.*.lines.*.weight_g'    => ['required', 'numeric', 'gt:0', 'max:100000'],
            'items.*.lines.*.quantity'    => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:1000'],
            'items.*.lines.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'request_text'                => ['required', 'string', 'max:4000'],
        ]);

        if (!$request->user()?->can_use_mcp_goods) {
            return Response::error('Recording product packaging is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        if (!$this->userCan($request, 'goods.edit') && !$this->userCan($request, 'compliance.edit')) {
            return Response::error('Recording product packaging needs goods or compliance edit permission, which this user does not have. Nothing was changed.');
        }

        $organisation = $this->organisationByCode($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        $items = collect($request->get('items'));

        [$orgStocks, $missing] = $this->orgStocksByCodes($organisation, $items->pluck('sko')->all());
        if ($missing) {
            return Response::error('Unknown SKO codes in '.$organisation->code.': '.implode(', ', $missing).'. Nothing was changed.');
        }
        $orgStocks = $orgStocks->keyBy('code');

        $results = $items->map(function (array $item) use ($orgStocks) {
            $lines = collect($item['lines']);
            $other = $lines->first(fn (array $line) => $line['material'] === SyncPackingDropdownComponents::OTHER && !trim((string)($line['description'] ?? '')));

            return ['sko' => $orgStocks[trim($item['sko'])]->code] + ($other ? ['result' => 'skipped', 'reason' => 'A piece with material other needs a description'] : []);
        })->all();

        $toSave = $items->keys()->reject(fn (int $index) => isset($results[$index]['result']));
        if ($toSave->isNotEmpty()) {
            $tradeUnitIds = DB::table('model_has_trade_units')
                ->where('model_type', 'OrgStock')
                ->whereIn('model_id', $toSave->map(fn (int $index) => $orgStocks[trim($items[$index]['sko'])]->id)->all())
                ->pluck('trade_unit_id')->unique()->values()->all();

            DB::transaction(function () use ($request, $organisation, $items, $orgStocks, $toSave, $tradeUnitIds, &$results) {
                $this->recordChange(
                    $request,
                    McpChangeTypeEnum::PRODUCT_PACKAGING,
                    'Product packaging weighed in '.$organisation->code.': '.$toSave->map(fn (int $index) => $results[$index]['sko'])->implode(', '),
                    ['trade_unit_ids' => $tradeUnitIds],
                    function () use ($items, $orgStocks, $toSave, &$results) {
                        foreach ($toSave as $index) {
                            /** @var OrgStock $orgStock */
                            $orgStock = $orgStocks[trim($items[$index]['sko'])];
                            try {
                                $saved            = RecordMeasuredTradeUnitPackaging::make()->handle($orgStock, $items[$index]['lines']);
                                $results[$index] += ['result' => $saved['result'], 'trade_unit' => $saved['trade_unit']->code];
                            } catch (ValidationException $exception) {
                                $results[$index] += ['result' => 'skipped', 'reason' => collect($exception->errors())->flatten()->first()];
                            }
                        }
                    }
                );
            });
        }

        return Response::json([
            'organisation'  => $organisation->code,
            'items'         => array_values($results),
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation code or slug the SKO codes belong to')->required(),
            'items'        => $schema->array()->items($schema->object([
                'sko'   => $schema->string()->description('SKO code')->required(),
                'lines' => $schema->array()->items($schema->object([
                    'material'    => $schema->string()->description('Packing material key, see the tool description')->required(),
                    'weight_g'    => $schema->number()->description('Weight of one piece in grams')->required(),
                    'quantity'    => $schema->number()->description('How many of these pieces per SKO; default 1'),
                    'description' => $schema->string()->description('What it is, e.g. "rubber band", "paper tag"; required for other'),
                ]))->description('Every packaging piece weighed on this SKO')->required(),
            ]))->description('Up to 100 SKOs')->required(),
            'request_text' => $schema->string()->description('The user\'s request, verbatim, kept in the change log')->required(),
        ];
    }
}
