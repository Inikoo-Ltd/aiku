<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Spatie\Permission\Models\Permission;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Lets an accounting manager correct a costed delivery, or name who makes the correction (HELP-4256). Whoever finished the costing may also reopen it
 * themselves for 2 working days and is then its assignee (HELP-4329). The put away stock keeps its value until the costing is finished again, then it is repriced
 * and its stock history rebuilt (EvaluateStockDeliveryCosting).
 */
class ReopenStockDeliveryCosting extends OrgAction
{
    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-supervisor.{$this->organisation->id}.accounting")
            || $this->stockDelivery->canUndoCosting($request->user());
    }

    public function rules(): array
    {
        return [
            'reason'      => ['required', 'string', 'max:1000'],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->stockDelivery->isCostingReopenable()) {
            $validator->errors()->add('state', __('Only a costed stock delivery can have its costing updated'));
        }

        $assigneeId = $validator->getData()['assignee_id'] ?? null;
        if ($assigneeId && !self::assignableUsers($this->stockDelivery)->contains('id', (int) $assigneeId)) {
            $validator->errors()->add('assignee_id', __('That person can not edit costings in this organisation'));
        }

    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        DB::transaction(function () use ($stockDelivery, $modelData) {
            $costs = self::costsSnapshot($stockDelivery);

            $stockDelivery->update([
                'is_costed' => false,
                'data'      => array_merge($stockDelivery->data, [
                    'costing_reopened' => [
                        'at'      => now()->toIso8601String(),
                        'user_id' => request()->user()?->id,
                        'reason'      => $modelData['reason'],
                        'assignee_id' => Arr::get($modelData, 'assignee_id') ? (int) $modelData['assignee_id'] : null,
                        'costs'       => $costs,
                    ],
                ]),
            ]);

            $stockDelivery->items()
                ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
                ->update(['is_costed' => false]);

            $stockDelivery->auditEvent     = 'costing_reopened';
            $stockDelivery->isCustomEvent  = true;
            $stockDelivery->auditCustomOld = $costs;
            $stockDelivery->auditCustomNew = array_filter([
                'reason'   => $modelData['reason'],
                'assignee' => User::find(Arr::get($modelData, 'assignee_id'))?->contact_name,
            ]);
            Event::dispatch(new AuditCustom($stockDelivery));
            $stockDelivery->isCustomEvent  = false;
            $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];

            UpdatePurchaseOrdersCostFromStockDelivery::run($stockDelivery);
        });

        return $stockDelivery->refresh();
    }

    /**
     * People who cost deliveries in this organisation and can be asked to make the update without being accounting managers.
     *
     * @return Collection<int, User>
     */
    public static function assignableUsers(StockDelivery $stockDelivery): Collection
    {
        $permissions = Permission::whereIn('name', [
            "procurement.{$stockDelivery->organisation_id}",
            "procurement.{$stockDelivery->organisation_id}.edit",
            "accounting.{$stockDelivery->organisation_id}",
            "accounting.{$stockDelivery->organisation_id}.edit",
        ])->pluck('name')->all();

        if (!$permissions) {
            return new Collection();
        }

        return User::permission($permissions)
            ->where('group_id', $stockDelivery->group_id)
            ->where('status', true)
            ->orderBy('contact_name')
            ->get(['users.id', 'users.contact_name', 'users.username']);
    }

    /**
     * @return array<string, string>
     */
    public static function costsSnapshot(StockDelivery $stockDelivery): array
    {
        $snapshot = ['total' => (string) $stockDelivery->cost_total];

        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with('orgStock:id,code')
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            /** @var StockDeliveryItem $item */
            $key = $item->orgStock?->code ?? '#'.$item->id;
            if (array_key_exists($key, $snapshot)) {
                $key .= ' #'.$item->id;
            }

            $snapshot[$key] = __('goods :items, extra :extra, shipping :shipping, duties :duties, tax :tax', [
                'items'    => (float) $item->cost_items,
                'extra'    => (float) $item->cost_extra,
                'shipping' => (float) $item->cost_shipping,
                'duties'   => (float) $item->cost_duties,
                'tax'      => (float) $item->cost_tax,
            ]);
        }

        return $snapshot;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        if (!$request->user()->authTo("org-supervisor.{$this->organisation->id}.accounting")) {
            $this->validatedData['assignee_id'] = $request->user()->id;
        }

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
