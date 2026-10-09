<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Production\Artefact\SetArtefactState;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Actions\Production\ManufactureTask\UpdateManufactureTask;
use App\Actions\Production\RawMaterial\StoreRawMaterial;
use App\Actions\Production\RawMaterial\UpdateRawMaterial;
use App\Actions\SysAdmin\McpChange\GetMcpChangeSnapshot;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\SysAdmin\Authorisation\ProductionPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\JobOrder;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Records go through the same store and update actions as the production pages. An artefact is
 * never created without its SKO: one created bare leaves an in-process twin beside the real one,
 * which is how awa ended up with ACLB-08 and ACLB-08_.
 */
#[Description('Shows, creates or edits the records a production is set up from: artefacts (what is made), raw materials (what it is made from, with their unit cost) and manufacture tasks (the steps people record on the tablets, e.g. POUR, LABEL, PACK). Without code it lists the records of that kind (filter with search, and artefacts with family). With code and no fields it shows that record. With fields it edits the record; to create one pass create=true. A new artefact needs its SKO: pass sko with an existing SKO code, or new_sko to create the stock, its trade unit and the SKO in one go. Recipes (which steps an artefact has and the raw materials each step uses) are set with production-recipe-tool. A raw material linked to a SKO takes its unit cost from the preferred supplier, so a unit_cost set by hand on it is overwritten. Risky edits (renaming a code, a unit cost moving by more than half, discontinuing an artefact still in open job orders) are refused with warnings until called again with accept naming them, which only the user may agree to; a SKO already used by another artefact is always refused. Show the user what you will create or change and write only after they confirmed in their own words, passing their request text. Only for users enrolled to set up production through their assistant.')]
class ProductionRecordsTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;
    use WithMcpProduction;

    private const array FIELDS = [
        'artefact'         => ['code', 'name', 'state', 'recommended_batch_size', 'shelf_life_days', 'family', 'department', 'sko', 'trade_unit'],
        'raw_material'     => ['code', 'description', 'type', 'state', 'unit', 'unit_cost', 'sko', 'trade_unit'],
        'manufacture_task' => ['code', 'name', 'description', 'status', 'is_piece_rate'],
    ];

    public function handle(Request $request): Response
    {
        $request->validate([
            'production'           => ['required', 'string'],
            'kind'                 => ['required', 'in:artefact,raw_material,manufacture_task'],
            'code'                 => ['sometimes', 'string'],
            'search'               => ['sometimes', 'string'],
            'family'               => ['sometimes', 'string'],
            'fields'               => ['sometimes', 'array'],
            'create'               => ['sometimes', 'boolean'],
            'new_sko'              => ['sometimes', 'array'],
            'new_sko.units'        => ['required_with:new_sko', 'integer', 'min:1'],
            'new_sko.name'         => ['sometimes', 'string', 'max:255'],
            'new_sko.description'  => ['sometimes', 'string', 'max:255'],
            'request_text'         => ['required_with:fields', 'string', 'max:4000'],
            'accept'               => ['sometimes', 'array'],
            'accept.*'             => ['string'],
        ]);

        $production = $this->resolveProduction($request);
        if ($production instanceof Response) {
            return $production;
        }

        $kind = (string) $request->string('kind');

        if (!$request->has('code')) {
            try {
                $familyId = $request->has('family') && $kind === 'artefact' ? $this->familyId($production, (string) $request->string('family')) : null;
            } catch (ValidationException $exception) {
                return $this->validationError($exception);
            }
            if ($request->has('family') && $kind === 'artefact' && !$familyId) {
                return Response::error("There is no artefact family {$request->string('family')} in {$production->code}. Families: ".ArtefactFamily::where('production_id', $production->id)->orderBy('code')->pluck('code')->unique()->implode(', ').'.');
            }

            return Response::json($this->index($production, $kind, $request->get('search'), $familyId));
        }

        $code   = trim((string) $request->string('code'));
        $record = $this->find($production, $kind, $code);

        if (!$request->has('fields')) {
            return $record ? Response::json($this->show($kind, $record)) : Response::error("There is no {$kind} {$code} in {$production->organisation->code}.");
        }

        if (!$this->canInProduction($request, $production, ProductionPermissionsEnum::PRODUCTION_RD_EDIT)) {
            return $this->cannotEditError($production);
        }

        $fields  = $request->get('fields');
        $unknown = array_diff(array_keys($fields), self::FIELDS[$kind]);
        if ($unknown) {
            return Response::error('Unknown fields for '.$kind.': '.implode(', ', $unknown).'. Allowed: '.implode(', ', self::FIELDS[$kind]).'. Nothing was changed.');
        }

        if ($request->boolean('create')) {
            if ($record) {
                return Response::error("{$kind} {$code} already exists in {$production->organisation->code}; call again without create to edit it. Nothing was changed.");
            }
        } elseif (!$record) {
            return Response::error("There is no {$kind} {$code} in {$production->organisation->code}. To create it call again with create=true. Nothing was changed.");
        }

        if ($record && $request->has('new_sko')) {
            return Response::error("new_sko is only for creating an artefact. To link {$code} to another SKO pass fields.sko. Nothing was changed.");
        }

        try {
            $modelData = $this->modelData($production, $kind, $fields);

            if ($kind === 'raw_material' && array_key_exists('unit_cost', $modelData) && ($modelData['org_stock_id'] ?? $record?->org_stock_id)) {
                return Response::error("{$code} is linked to a SKO, so its unit cost comes from the preferred supplier and a cost set here would be overwritten. Change the supplier cost instead, or unlink the SKO (sko: null) first. Nothing was changed.");
            }

            if (!$record && $kind === 'artefact' && !Arr::get($modelData, 'org_stock_id') && !$request->has('new_sko')) {
                return Response::error('A new artefact needs its SKO: pass fields.sko with an existing SKO code, or new_sko {units, name?, description?} to create the stock, trade unit and SKO with the artefact code. Nothing was changed.');
            }

            if ($refusal = $this->skoTaken($production, $record, $modelData) ?? $this->unacceptedWarnings($request, $this->warnings($kind, $record, $modelData))) {
                return $refusal;
            }

            $record = $this->recordChange(
                $request,
                McpChangeTypeEnum::PRODUCTION_RECORD,
                ($record ? 'Edit ' : 'Create ').str_replace('_', ' ', $kind).' '.$code.' in '.$production->code,
                ['kind' => $kind, 'organisation_id' => $production->organisation_id, 'production_id' => $production->id, 'id' => $record?->id, 'code' => $code],
                fn () => DB::transaction(fn () => $record
                    ? $this->update($kind, $record, $modelData)
                    : $this->store($production, $kind, $code, $modelData, $request->get('new_sko'))),
                ['production' => $production->code]
            );
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        return Response::json([
            'changed'       => (bool) $this->mcpChange,
            'change_log_id' => $this->mcpChange?->id,
            ...$this->show($kind, $record->refresh()),
        ]);
    }

    /**
     * One SKO, one artefact: a second artefact on the same SKO is how the half-made twins
     * beside ACLB-08_ came about, and stock would no longer know which recipe makes it.
     */
    private function skoTaken(Production $production, Artefact|RawMaterial|ManufactureTask|null $record, array $modelData): ?Response
    {
        if (!($record instanceof Artefact || ($record === null && array_key_exists('org_stock_id', $modelData))) || !Arr::get($modelData, 'org_stock_id')) {
            return null;
        }

        $other = Artefact::where('org_stock_id', $modelData['org_stock_id'])
            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
            ->first();

        return $other ? Response::error("That SKO is already the SKO of artefact {$other->code} ({$other->state->value}). One SKO belongs to one artefact; edit {$other->code} instead, or unlink it there first. Nothing was changed.") : null;
    }

    /**
     * @return array<string, string>
     */
    private function warnings(string $kind, Artefact|RawMaterial|ManufactureTask|null $record, array $modelData): array
    {
        if (!$record) {
            return [];
        }

        $warnings = [];

        if (isset($modelData['code']) && $modelData['code'] !== $record->code) {
            $warnings['rename'] = "This renames {$record->code} to {$modelData['code']}. Labels, sheets and people still using the old code will no longer find it.";
        }

        if ($record instanceof RawMaterial && array_key_exists('unit_cost', $modelData) && (float) $record->unit_cost > 0
            && abs((float) $modelData['unit_cost'] - (float) $record->unit_cost) / (float) $record->unit_cost > 0.5) {
            $warnings['cost_jump'] = "The unit cost of {$record->code} goes from {$record->unit_cost} to {$modelData['unit_cost']}, more than half up or down; check it is not a typo (e.g. per gram vs per kilo). It changes the cost of every recipe using it.";
        }

        if ($record instanceof Artefact && in_array($modelData['state'] ?? null, [ArtefactStateEnum::DISCONTINUED->value, ArtefactStateEnum::DORMANT->value])) {
            $openJobOrders = JobOrder::whereIn('state', JobOrderStateEnum::open())
                ->whereHas('jobOrderItems', fn ($query) => $query->where('artefact_id', $record->id))
                ->get(['id', 'reference'])
                ->map(fn (JobOrder $jobOrder) => $jobOrder->reference ?? '#'.$jobOrder->id);
            if ($openJobOrders->isNotEmpty()) {
                $warnings['open_job_orders'] = "{$record->code} is still in open job orders: ".$openJobOrders->implode(', ').'. They are not cancelled by this.';
            }
        }

        return $warnings;
    }

    /**
     * Turns the codes the assistant knows (family, SKO, trade unit) into the ids the actions take.
     */
    private function modelData(Production $production, string $kind, array $fields): array
    {
        $lookups = [
            'family'     => ['artefact_family_id', fn ($code) => $this->familyId($production, $code)],
            'department' => ['artefact_department_id', fn ($code) => ArtefactDepartment::where('production_id', $production->id)->whereRaw('lower(code) = ?', [strtolower($code)])->value('id')],
            'sko'        => ['org_stock_id', fn ($code) => OrgStock::where('organisation_id', $production->organisation_id)->whereRaw('lower(code) = ?', [strtolower($code)])->value('id')],
            'trade_unit' => ['trade_unit_id', fn ($code) => TradeUnit::where('group_id', $production->group_id)->whereRaw('lower(code) = ?', [strtolower($code)])->value('id')],
        ];

        $modelData = [];
        foreach ($fields as $field => $value) {
            if (!isset($lookups[$field])) {
                $modelData[$field] = $value;
                continue;
            }

            [$column, $lookup] = $lookups[$field];
            $id = $value === null ? null : $lookup((string) $value);
            if ($value !== null && !$id) {
                $options = match ($field) {
                    'family'     => ' Families: '.ArtefactFamily::where('production_id', $production->id)->orderBy('code')->pluck('code')->implode(', ').'.',
                    'department' => ' Departments: '.ArtefactDepartment::where('production_id', $production->id)->orderBy('code')->pluck('code')->implode(', ').'.',
                    default      => '',
                };
                throw ValidationException::withMessages([$field => "There is no {$field} {$value} in {$production->organisation->code}.".$options]);
            }
            $modelData[$column] = $id;
        }

        if ($kind === 'artefact' && Arr::get($modelData, 'org_stock_id') && !array_key_exists('trade_unit_id', $modelData)
            && $tradeUnitId = $this->singleTradeUnitId(OrgStock::find($modelData['org_stock_id'])->stock)) {
            $modelData['trade_unit_id'] = $tradeUnitId;
        }

        if ($kind === 'artefact' && array_key_exists('state', $modelData) && !ArtefactStateEnum::tryFrom((string) $modelData['state'])) {
            throw ValidationException::withMessages(['state' => 'state must be one of: '.implode(', ', ArtefactStateEnum::values()).'.']);
        }

        return $modelData;
    }

    private function familyId(Production $production, string $identifier): ?int
    {
        $ids = ArtefactFamily::where('production_id', $production->id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [strtolower($identifier)])->orWhereRaw('lower(code) = ?', [strtolower($identifier)]))
            ->pluck('slug', 'id');

        if ($ids->count() > 1) {
            throw ValidationException::withMessages(['family' => "More than one family is coded {$identifier}; pass the slug of the one you mean: ".$ids->implode(', ').'.']);
        }

        return $ids->keys()->first();
    }

    private function store(Production $production, string $kind, string $code, array $modelData, ?array $newSko): Artefact|RawMaterial|ManufactureTask
    {
        $modelData['code'] = $code;

        return match ($kind) {
            'artefact'         => StoreArtefact::make()->action($production, $newSko && !Arr::get($modelData, 'org_stock_id') ? [...$modelData, ...$this->newSko($production, $modelData, $newSko)] : $modelData),
            'raw_material'     => StoreRawMaterial::make()->action($production, $modelData),
            'manufacture_task' => StoreManufactureTask::make()->action($production, $modelData),
        };
    }

    /**
     * An existing group stock with this code only needs its SKO in this organisation.
     */
    private function newSko(Production $production, array $modelData, array $newSko): array
    {
        $stock = Stock::where('group_id', $production->group_id)->whereRaw('lower(code) = ?', [strtolower($modelData['code'])])->first()
            ?? StoreStock::make()->action($production->group, [
                'code'       => $modelData['code'],
                'name'       => $newSko['name'] ?? $modelData['name'] ?? $modelData['code'],
                'units'      => $newSko['units'],
                'trade_unit' => ['description' => $newSko['description'] ?? $newSko['name'] ?? $modelData['name'] ?? $modelData['code']],
            ]);

        $orgStock = OrgStock::where('organisation_id', $production->organisation_id)->where('stock_id', $stock->id)->first()
            ?? StoreOrgStock::make()->action($production->organisation, $stock);

        if ($taken = Artefact::where('org_stock_id', $orgStock->id)->value('code')) {
            throw ValidationException::withMessages(['new_sko' => "The SKO {$orgStock->code} already exists and is the SKO of artefact {$taken}. Edit {$taken} instead."]);
        }

        return [
            'org_stock_id'  => $orgStock->id,
            'trade_unit_id' => $this->singleTradeUnitId($stock),
        ];
    }

    private function singleTradeUnitId(Stock $stock): ?int
    {
        $tradeUnitIds = $stock->tradeUnits()->pluck('trade_units.id');

        return $tradeUnitIds->count() === 1 ? $tradeUnitIds->first() : null;
    }

    private function update(string $kind, Artefact|RawMaterial|ManufactureTask $record, array $modelData): Artefact|RawMaterial|ManufactureTask
    {
        if ($record instanceof Artefact) {
            if ($state = Arr::pull($modelData, 'state')) {
                SetArtefactState::make()->action($record, ArtefactStateEnum::from($state));
            }

            return $modelData ? UpdateArtefact::make()->action($record, $modelData) : $record;
        }

        return match ($kind) {
            'raw_material'     => UpdateRawMaterial::make()->action($record, $modelData),
            'manufacture_task' => UpdateManufactureTask::make()->action($record, $modelData),
        };
    }

    private function find(Production $production, string $kind, string $code): Artefact|RawMaterial|ManufactureTask|null
    {
        return $this->query($production, $kind)->whereRaw('lower(code) = ?', [strtolower($code)])->first();
    }

    private function query(Production $production, string $kind)
    {
        return match ($kind) {
            'artefact'         => Artefact::where('production_id', $production->id),
            'raw_material'     => RawMaterial::where('organisation_id', $production->organisation_id),
            'manufacture_task' => ManufactureTask::where('production_id', $production->id),
        };
    }

    private function index(Production $production, string $kind, ?string $search, ?int $familyId): array
    {
        $records = $this->query($production, $kind)
            ->when($familyId, fn ($query) => $query->where('artefact_family_id', $familyId))
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('code ilike ?', ['%'.$search.'%'])
                ->orWhereRaw(($kind === 'raw_material' ? 'description' : 'name').' ilike ?', ['%'.$search.'%'])))
            ->with(match ($kind) {
                'artefact'         => ['artefactFamily:id,code', 'artefactDepartment:id,code', 'orgStock:id,code', 'tradeUnit:id,code'],
                'raw_material'     => ['orgStock:id,code', 'tradeUnit:id,code', 'organisation.currency'],
                'manufacture_task' => [],
            })
            ->orderBy('code')
            ->limit(300)
            ->get();

        return [
            'production' => $production->code,
            'kind'       => $kind,
            'records'    => $records->map(fn ($record) => $this->show($kind, $record))->all(),
            'truncated'  => $records->count() === 300,
        ];
    }

    private function show(string $kind, Artefact|RawMaterial|ManufactureTask $record): array
    {
        $fields = Arr::only($record->getAttributes(), GetMcpChangeSnapshot::PRODUCTION_RECORD_FIELDS[$kind]);
        unset($fields['id'], $fields['org_stock_id'], $fields['trade_unit_id'], $fields['artefact_family_id'], $fields['artefact_department_id']);

        return match ($kind) {
            'artefact'         => [...$fields, 'family' => $record->artefactFamily?->code, 'department' => $record->artefactDepartment?->code, 'sko' => $record->orgStock?->code, 'trade_unit' => $record->tradeUnit?->code],
            'raw_material'     => [...$fields, 'sko' => $record->orgStock?->code, 'trade_unit' => $record->tradeUnit?->code, 'currency' => $record->organisation->currency->code],
            'manufacture_task' => $fields,
        };
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'production'   => $schema->string()->description('Production slug or code, e.g. awa')->required(),
            'kind'         => $schema->string()->enum(['artefact', 'raw_material', 'manufacture_task'])->required(),
            'code'         => $schema->string()->description('Code of the record to show, edit or create. Omit to list'),
            'search'       => $schema->string()->description('When listing: text in code or name/description'),
            'family'       => $schema->string()->description('When listing artefacts: only those in this family (code or slug)'),
            'fields'       => $schema->object()->description('Fields to set. artefact: code, name, state (in_process, active, dormant, discontinued), recommended_batch_size, shelf_life_days, family (artefact family code), department, sko (SKO code), trade_unit. raw_material: code, description, type (stock, consumable, intermediate), state, unit (unit, pack, carton, liter, kilogram), unit_cost (organisation currency, per unit), sko, trade_unit (unit_cost only when not linked to a SKO). manufacture_task: code, name, description, status (true = active), is_piece_rate. Null clears a link'),
            'create'       => $schema->boolean()->description('true to create the record with this code'),
            'new_sko'      => $schema->object()->description('New artefact only, when its SKO does not exist yet: {units: trade units per SKO, name?: SKO name, description?: trade unit description}. Creates the stock and trade unit (group-wide, with the artefact code) and the SKO in this organisation'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required when writing'),
            'accept'       => $schema->array()->items($schema->string())->description('Warning codes the user has read and agreed to, exactly as a previous refusal listed them. Never send one the user has not seen'),
        ];
    }
}
