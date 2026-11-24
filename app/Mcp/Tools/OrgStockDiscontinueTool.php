<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Changes the state of SKOs (organisation stock) after org-stock-discontinue-preview-tool has been shown to the user and they confirmed in their own words. Group wide by default: every organisation carrying the same stock moves together; organisation_states keeps named organisations on a different state. A reason is required unless going back to active. effective_at in the future schedules the change instead of applying it. Pass expected_updated_at from the preview so a SKO that moved since is refused. Every change is audited with the user, the reason and request_text. Setting SKOs back to active does NOT bring their products back: after the preview, show the user the discontinued products that can come back and those that stay discontinued with the reason, per shop, ask which to bring back (default: all in the reactivate list) and pass reactivate_products only after they confirm in their own words; closed shops never get products back. The result lists the reactivated products, created or reopened pages and refused ids. Only for users enrolled to discontinue SKOs through their assistant.')]
class OrgStockDiscontinueTool extends AikuOrgStockDiscontinueTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'          => ['required', 'string'],
            'codes'                 => ['required', 'array', 'min:1', 'max:50'],
            'codes.*'               => ['string'],
            'state'                 => ['required', 'in:active,discontinuing,discontinued,suspended'],
            'reason'                => ['required_unless:state,active', 'nullable', 'string', 'max:1000'],
            'request_text'          => ['required', 'string', 'max:4000'],
            'effective_at'          => ['sometimes', 'nullable', 'date'],
            'organisation_states'   => ['sometimes', 'array'],
            'organisation_states.*' => ['in:active,discontinuing,discontinued,suspended'],
            'expected_updated_at'   => ['sometimes', 'array'],
            'expected_updated_at.*' => ['date'],
            'reactivate_products'   => ['sometimes', 'array', 'max:500', 'prohibited_unless:state,active'],
            'reactivate_products.*' => ['integer'],
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
        if ($missing) {
            return Response::error('Unknown SKO codes in '.$organisation->code.': '.implode(', ', $missing).'. Nothing was changed.');
        }

        $expected = collect($request->get('expected_updated_at', []))
            ->mapWithKeys(fn ($value, $code) => [$orgStocks->firstWhere('code', $code)?->id ?? $code => $value])
            ->all();

        $groupOrgStockIds = OrgStock::whereIn('stock_id', $orgStocks->pluck('stock_id')->filter())
            ->orWhereIn('id', $orgStocks->pluck('id'))
            ->pluck('id')
            ->all();

        try {
            $stats = $this->recordChange(
                $request,
                McpChangeTypeEnum::ORG_STOCK_STATE,
                'SKOs '.$orgStocks->pluck('code')->implode(', ').' ('.$organisation->code.') to '.$request->string('state'),
                ['org_stock_ids' => $groupOrgStockIds],
                fn () => DiscontinueOrgStocks::make()->action($organisation, array_filter([
                'org_stock_ids'       => $orgStocks->pluck('id')->all(),
                'state'               => $request->string('state')->toString(),
                'reason'              => $request->get('reason'),
                'effective_at'        => $request->get('effective_at'),
                'organisation_states' => $request->get('organisation_states'),
                'expected_updated_at' => $expected,
                'reactivate_products' => $request->get('reactivate_products'),
                'source'              => 'mcp',
                'request_text'        => $request->string('request_text')->toString(),
            ], fn ($value) => $value !== null), $request->user())
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed; preview again.');
        }

        $reactivation = $stats['reactivation'] ?? null;

        if ($reactivation && $this->mcpChange) {
            $this->mcpChange->update(['data' => array_merge($this->mcpChange->data, [
                'reactivated_products' => $reactivation['reactivated'],
                'refused_products'     => $reactivation['refused'],
                'failed_products'      => $reactivation['failed'],
                'families_reopened'    => $reactivation['families_reopened'],
            ])]);
        }

        return Response::json([
            'organisation' => $organisation->code,
            'codes'        => $orgStocks->pluck('code')->all(),
            'state'        => $request->string('state')->toString(),
            'result'        => $stats,
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation'        => $schema->string()->description('Organisation code or slug the SKO codes belong to')->required(),
            'codes'               => $schema->array()->items($schema->string())->description('SKO codes, up to 50')->required(),
            'state'               => $schema->string()->description('Target state for every organisation: discontinuing, discontinued, suspended or active')->required(),
            'reason'              => $schema->string()->description('Why, in the user\'s words; required unless state is active'),
            'request_text'        => $schema->string()->description('The user\'s request, verbatim, kept in the audit')->required(),
            'effective_at'        => $schema->string()->description('Date (Y-m-d); a future date schedules the change'),
            'organisation_states' => $schema->object()->description('Exceptions per organisation code, e.g. {"sk": "active"}'),
            'reactivate_products' => $schema->array()->items($schema->integer())->description('Only with state active: ids of discontinued products to bring back, taken from the preview products_to_reactivate.reactivate list. Setting SKOs to active does not bring products back. Pass only after the user confirmed which ones in their own words; ids outside that list are refused and closed shops never get products back.'),
            'expected_updated_at' => $schema->object()->description('updated_at per SKO code as returned by the preview; a SKO that moved since is refused'),
        ];
    }
}
