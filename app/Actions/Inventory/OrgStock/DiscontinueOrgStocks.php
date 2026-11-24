<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Throwable;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Changes the state of an organisation stock and, by default, of every sibling that carries the
 * same stock in the other organisations. Named organisations may be given a different target state.
 *
 * The state itself goes through UpdateOrgStock, which hydrates stock, trade units, products and
 * warehouses but never changes a product's state: the products of an org stock set back to active
 * are reactivated only when the caller passes them in reactivate_products (see
 * GetProductsToReactivateWithOrgStocks for which ones are allowed). On top of it every org stock gets one custom audit entry
 * with the scope, the reason, the effective date and where the request came from, so the history
 * says who asked, why and how, not only that a column moved.
 *
 * An effective date in the future stores the request on the org stock and changes nothing yet;
 * ApplyScheduledOrgStockStateChanges runs it when the day comes.
 */
class DiscontinueOrgStocks extends OrgAction
{
    public const string SCHEDULED_KEY = 'scheduled_state_change';

    /** @var Collection<int, OrgStock> */
    private Collection $orgStocks;

    private ?User $user = null;

    /**
     * @return array{changed: int, unchanged: int, scheduled: int, reactivation?: array<string, array<int, array<string, mixed>>>}
     */
    public function handle(Collection $orgStocks, array $modelData): array
    {
        $this->assertCanChangeStatus();

        $targetState = OrgStockStateEnum::from($modelData['state']);
        $overrides   = Arr::get($modelData, 'organisation_states', []);
        $effectiveAt = Arr::get($modelData, 'effective_at') ? Carbon::parse($modelData['effective_at']) : now();
        $scheduled   = $effectiveAt->isFuture();

        $stats = ['changed' => 0, 'unchanged' => 0, 'scheduled' => 0];

        $reactivateProductIds = $scheduled || $targetState !== OrgStockStateEnum::ACTIVE ? [] : Arr::get($modelData, 'reactivate_products', []);

        $set = Arr::get($modelData, 'scope', 'group') === 'group' ? self::groupSet($orgStocks) : $orgStocks->load('organisation');

        $activatedIds = [];

        DB::transaction(function () use ($set, $overrides, $targetState, $effectiveAt, $scheduled, $modelData, &$stats, &$activatedIds): void {
            foreach ($set as $orgStock) {
                $organisationCode = $orgStock->organisation->code;
                $state            = OrgStockStateEnum::from(Arr::get($overrides, $organisationCode, $targetState->value));

                $record = [
                    'from_state'    => $orgStock->state->value,
                    'to_state'      => $state->value,
                    'scope'         => Arr::get($modelData, 'scope', 'group'),
                    'group_state'   => $targetState->value,
                    'overrides'     => (object) $overrides,
                    'reason'        => Arr::get($modelData, 'reason'),
                    'effective_at'  => $effectiveAt->toIso8601String(),
                    'source'        => Arr::get($modelData, 'source', 'ui'),
                    'request_text'  => Arr::get($modelData, 'request_text'),
                    'requested_by'  => $this->user?->username,
                ];

                if ($scheduled) {
                    $orgStock->update(['data' => array_merge($orgStock->data, [self::SCHEDULED_KEY => $record])]);
                    $this->audit($orgStock, 'schedule_state_change', $record);
                    $stats['scheduled']++;
                    continue;
                }

                if (Arr::has($orgStock->data, self::SCHEDULED_KEY)) {
                    $orgStock->update(['data' => Arr::except($orgStock->data, [self::SCHEDULED_KEY])]);
                }

                if ($state === OrgStockStateEnum::ACTIVE) {
                    $activatedIds[] = $orgStock->id;
                }

                if ($orgStock->state === $state) {
                    $stats['unchanged']++;
                    continue;
                }

                UpdateOrgStock::make()->action($orgStock, ['state' => $state->value]);
                $this->audit($orgStock, 'state_change', $record);
                $stats['changed']++;
            }
        });

        if ($reactivateProductIds) {
            $stats['reactivation'] = $this->reactivateProducts(OrgStock::whereIn('id', $activatedIds)->get(), $reactivateProductIds);
        }

        Cache::forget(ShowGoodsDashboard::cacheKey($this->organisation->group_id));

        return $stats;
    }

    /**
     * Every product is independent: one that fails (a page url already taken, for instance) is
     * reported and the others still come back. The families touched are always hydrated, and a
     * family that is active again gets its closed page reopened here, not in UpdateProductCategory,
     * because that action also runs in bulk hydrations where a closed page may be deliberate.
     *
     * @param  Collection<int, OrgStock>  $orgStocks
     * @param  array<int, int>  $productIds
     * @return array{reactivated: array<int, array<string, mixed>>, refused: array<int, array<string, mixed>>, failed: array<int, array<string, mixed>>, families_reopened: array<int, array<string, mixed>>}
     */
    private function reactivateProducts(Collection $orgStocks, array $productIds): array
    {
        $allowed    = collect(GetProductsToReactivateWithOrgStocks::run($orgStocks)['reactivate'])->keyBy('id');
        $allowedIds = $allowed->keys()->intersect($productIds);

        $refused = collect($productIds)
            ->unique()
            ->diff($allowedIds)
            ->map(fn (int $id) => ['id' => $id, 'reason' => __('Not in the list of products that can come back with these SKOs')])
            ->values()
            ->all();

        $reactivated = [];
        $failed      = [];
        $families    = [];

        foreach (Product::whereIn('id', $allowedIds)->with(['shop.website', 'webpage', 'family'])->get() as $product) {
            try {
                $webpageBefore = $product->webpage?->state?->value;
                $product       = UpdateProduct::make()->action($product, ['state' => ProductStateEnum::ACTIVE, 'is_for_sale' => true]);
                $webpageAction = null;

                if (!$product->webpage && $product->shop->website) {
                    if ($product->is_main) {
                        $webpage = StoreProductWebpage::run($product);
                        PublishWebpage::make()->action($webpage, ['comment' => 'reactivated with SKO']);
                        $webpageAction = 'created';
                    } else {
                        $webpageAction = 'not_created_not_main_product';
                    }
                } elseif ($webpageBefore === 'closed' && $product->webpage?->refresh()->state?->value !== 'closed') {
                    $webpageAction = 'reopened';
                }

                $reactivated[] = [
                    'id'        => $product->id,
                    'code'      => $product->code,
                    'shop_code' => $product->shop->code,
                    'webpage'   => $webpageAction,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $failed[] = [
                    'id'        => $product->id,
                    'code'      => $product->code,
                    'shop_code' => $product->shop?->code,
                    'error'     => $exception instanceof ValidationException ? implode(' ', $exception->validator->errors()->all()) : $exception->getMessage(),
                ];
            }

            if ($product->family) {
                $families[$product->family->id] = $product->family;
            }
        }

        $familiesReopened = [];

        foreach ($families as $family) {
            try {
                FamilyHydrateProducts::run($family);

                $family = $family->refresh();
                $page   = $family->webpage;

                if ($family->state === ProductCategoryStateEnum::ACTIVE && $page && $page->state === WebpageStateEnum::CLOSED) {
                    ReopenWebpage::run($page);
                    $familiesReopened[] = ['id' => $family->id, 'code' => $family->code];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return ['reactivated' => $reactivated, 'refused' => $refused, 'failed' => $failed, 'families_reopened' => $familiesReopened];
    }

    /**
     * The scheduled sweep runs with no user because it only executes a request that was already
     * authorised when it was made; every other door carries a user and goes through here.
     */
    private function assertCanChangeStatus(): void
    {
        if (!$this->user) {
            return;
        }

        if (!self::canChangeGroupStatus($this->user)) {
            throw ValidationException::withMessages([
                'scope' => __('Discontinuing SKOs needs the Supply Chain Manager permission'),
            ]);
        }
    }

    public static function canChangeGroupStatus(User $user): bool
    {
        return $user->authTo(GroupPermissionsEnum::SUPPLY_CHAIN->value);
    }

    public static function canChangeStatus(User $user, Organisation $organisation): bool
    {
        return self::canChangeGroupStatus($user);
    }

    /**
     * @return Collection<int, OrgStock>
     */
    public static function groupSet(Collection $orgStocks): Collection
    {
        $stockIds = $orgStocks->pluck('stock_id')->filter()->unique();

        return OrgStock::whereIn('stock_id', $stockIds)
            ->orWhereIn('id', $orgStocks->pluck('id'))
            ->with('organisation')
            ->get()
            ->unique('id')
            ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
            ->values();
    }

    private function audit(OrgStock $orgStock, string $event, array $record): void
    {
        $orgStock->auditEvent     = $event;
        $orgStock->isCustomEvent  = true;
        $orgStock->auditCustomOld = ['state' => $record['from_state']];
        $orgStock->auditCustomNew = Arr::except($record, ['from_state']);

        Event::dispatch(new AuditCustom($orgStock));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canChangeStatus($request->user(), $this->organisation);
    }

    public function rules(): array
    {
        $states = [
            OrgStockStateEnum::ACTIVE->value,
            OrgStockStateEnum::DISCONTINUING->value,
            OrgStockStateEnum::DISCONTINUED->value,
            OrgStockStateEnum::SUSPENDED->value,
        ];

        return [
            'org_stock_ids'         => ['required', 'array', 'max:200'],
            'org_stock_ids.*'       => ['integer'],
            'state'                 => ['required', Rule::in($states)],
            'scope'                 => ['sometimes', Rule::in(['group', 'organisation'])],
            'organisation_states'   => ['sometimes', 'array'],
            'organisation_states.*' => [Rule::in($states)],
            'reason'                => ['required_unless:state,'.OrgStockStateEnum::ACTIVE->value, 'nullable', 'string', 'max:1000'],
            'effective_at'          => ['sometimes', 'nullable', 'date'],
            'expected_updated_at'   => ['sometimes', 'array'],
            'expected_updated_at.*' => ['date'],
            'source'                => ['sometimes', Rule::in(['ui', 'mcp', 'schedule'])],
            'request_text'          => ['sometimes', 'nullable', 'string', 'max:4000'],
            'reactivate_products'   => ['sometimes', 'array', 'max:500', 'prohibited_unless:state,'.OrgStockStateEnum::ACTIVE->value],
            'reactivate_products.*' => ['integer'],
        ];
    }

    /**
     * The stale guard compares the updated_at the caller saw at preview time with what the row
     * carries now; any drift means someone else touched the stock and the caller must look again.
     * Overrides may only name organisations that exist: discontinuing is a supply chain decision,
     * so whoever may do it may do it in any organisation.
     */
    public function afterValidator(Validator $validator): void
    {
        $input = $validator->getData();

        $this->orgStocks = OrgStock::where('organisation_id', $this->organisation->id)
            ->whereIn('id', Arr::wrap(Arr::get($input, 'org_stock_ids', [])))
            ->get();

        if ($this->orgStocks->isEmpty()) {
            $validator->errors()->add('org_stock_ids', __('No SKO found in this organisation'));

            return;
        }

        foreach (Arr::get($input, 'expected_updated_at', []) as $orgStockId => $expected) {
            $orgStock = $this->orgStocks->firstWhere('id', (int) $orgStockId);
            if ($orgStock && !$orgStock->updated_at->equalTo(Carbon::parse($expected))) {
                $validator->errors()->add('expected_updated_at', __('SKO :code changed since the preview, look again before confirming', ['code' => $orgStock->code]));
            }
        }

        foreach (array_keys(Arr::get($input, 'organisation_states', [])) as $organisationCode) {
            if (!Organisation::where('code', $organisationCode)->exists()) {
                $validator->errors()->add('organisation_states', __('Unknown organisation :organisation', ['organisation' => $organisationCode]));
            }
        }
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): RedirectResponse
    {
        $this->user = $request->user();
        $this->initialisationFromWarehouse($warehouse, $request);
        $stats = $this->handle($this->orgStocks, $this->validatedData);

        return back()->with('reactivation', $stats['reactivation'] ?? null);
    }

    public function action(Organisation $organisation, array $modelData, ?User $user = null): array
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->initialisation($organisation, $modelData);

        return $this->handle($this->orgStocks, $this->validatedData);
    }
}
