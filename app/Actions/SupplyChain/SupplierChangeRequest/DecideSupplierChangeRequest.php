<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierChangeRequest;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\DeactivateSupplier;
use App\Actions\SupplyChain\Supplier\MergeSuppliers;
use App\Enums\SupplyChain\SupplierChangeRequest\SupplierChangeRequestStateEnum;
use App\Enums\SupplyChain\SupplierChangeRequest\SupplierChangeRequestTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SupplyChain\SupplierChangeRequest;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

/**
 * The directors approve a suggestion, which deactivates or merges the supplier, or keep the supplier as it
 * is. Who decided, when and why stays on the suggestion.
 */
class DecideSupplierChangeRequest extends OrgAction
{
    private SupplierChangeRequest $supplierChangeRequest;

    public static function canDecide(User $user): bool
    {
        return $user->hasRole(RolesEnum::GROUP_ADMIN->value);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || self::canDecide($request->user());
    }

    public string $jobQueue = 'urgent';

    public int $jobTimeout = 1800;

    public int $jobTries = 1;

    private const int STALE_AFTER_MINUTES = 35;

    public static function progressKey(SupplierChangeRequest $supplierChangeRequest): string
    {
        return 'supplier-change-request-progress:'.$supplierChangeRequest->id;
    }

    public static function putProgress(SupplierChangeRequest $supplierChangeRequest, string $step, int $done = 0, int $total = 0, ?string $message = null): void
    {
        $key       = self::progressKey($supplierChangeRequest);
        $startedAt = $step === 'queued' ? null : Arr::get(Cache::get($key) ?? [], 'started_at');

        Cache::put($key, [
            'step'       => $step,
            'done'       => $done,
            'total'      => $total,
            'message'    => $message,
            'started_at' => $startedAt ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], 3600);
    }

    /**
     * @return array{step: string, done: int, total: int, message: ?string, started_at: string, updated_at: string}|null
     */
    public static function getProgress(SupplierChangeRequest $supplierChangeRequest): ?array
    {
        $progress = Cache::get(self::progressKey($supplierChangeRequest));

        $updatedAt = Arr::get($progress ?? [], 'updated_at');
        $stale     = !$updatedAt || now()->subMinutes(self::STALE_AFTER_MINUTES)->isAfter($updatedAt);
        if ($progress && !in_array($progress['step'], ['done', 'failed']) && $stale) {
            return [...$progress, 'step' => 'failed', 'message' => __('The approval stopped responding, approve again')];
        }

        return $progress;
    }

    public static function isRunning(SupplierChangeRequest $supplierChangeRequest): bool
    {
        $step = Arr::get(self::getProgress($supplierChangeRequest) ?? [], 'step');

        return $step !== null && !in_array($step, ['done', 'failed']);
    }

    public function handle(SupplierChangeRequest $supplierChangeRequest, array $modelData): SupplierChangeRequest
    {
        $approve  = (bool) $modelData['approve'];
        $progress = fn (string $step, int $done = 0, int $total = 0) => self::putProgress($supplierChangeRequest, $step, $done, $total);

        if ($approve) {
            match ($supplierChangeRequest->type) {
                SupplierChangeRequestTypeEnum::DEACTIVATE => $this->deactivate($supplierChangeRequest, $progress),
                SupplierChangeRequestTypeEnum::MERGE      => MergeSuppliers::run($supplierChangeRequest->supplier, $supplierChangeRequest->targetSupplier, $progress),
            };
        }

        $progress('saving');

        $supplierChangeRequest->update([
            'state'              => $approve ? SupplierChangeRequestStateEnum::APPROVED : SupplierChangeRequestStateEnum::REJECTED,
            'decided_by_user_id' => Arr::get($modelData, 'decided_by_user_id'),
            'decided_at'         => now(),
            'decision_note'      => Arr::get($modelData, 'decision_note'),
        ]);

        if ($approve) {
            $this->closeOtherSuggestions($supplierChangeRequest, Arr::get($modelData, 'decided_by_user_id'));
        }

        $progress('done');

        return $supplierChangeRequest;
    }

    private function deactivate(SupplierChangeRequest $supplierChangeRequest, callable $progress): void
    {
        $progress('deactivating');
        DeactivateSupplier::run($supplierChangeRequest->supplier);
    }

    /**
     * Once a supplier is deactivated or merged away, the other suggestions on it, or merging into it, no
     * longer apply.
     */
    private function closeOtherSuggestions(SupplierChangeRequest $supplierChangeRequest, ?int $userId): void
    {
        SupplierChangeRequest::where('state', SupplierChangeRequestStateEnum::PENDING)
            ->where('id', '!=', $supplierChangeRequest->id)
            ->where(fn ($query) => $query->where('supplier_id', $supplierChangeRequest->supplier_id)->orWhere('target_supplier_id', $supplierChangeRequest->supplier_id))
            ->update([
                'state'              => SupplierChangeRequestStateEnum::REJECTED,
                'decided_by_user_id' => $userId,
                'decided_at'         => now(),
                'decision_note'      => __(':code was :decision in another suggestion', [
                    'code'     => $supplierChangeRequest->supplier->code,
                    'decision' => $supplierChangeRequest->type === SupplierChangeRequestTypeEnum::MERGE ? __('merged') : __('deactivated'),
                ]),
            ]);
    }

    public function rules(): array
    {
        return [
            'approve'            => ['required', 'boolean'],
            'decision_note'      => ['required_if:approve,false', 'nullable', 'string', 'max:2000'],
            'decided_by_user_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->supplierChangeRequest->state !== SupplierChangeRequestStateEnum::PENDING) {
            $validator->errors()->add('approve', __('This suggestion was already decided'));

            return;
        }

        if (!$this->get('approve')) {
            return;
        }

        if (self::isRunning($this->supplierChangeRequest)) {
            $validator->errors()->add('approve', __('This suggestion is already being approved'));

            return;
        }

        $inactive = collect([$this->supplierChangeRequest->supplier, $this->supplierChangeRequest->targetSupplier])->filter()->reject(fn ($supplier) => $supplier->status);
        if ($inactive->isNotEmpty()) {
            $validator->errors()->add('approve', __(':codes is no longer active', ['codes' => $inactive->pluck('code')->implode(', ')]));

            return;
        }

        foreach ($this->blockers() as $blocker) {
            $validator->errors()->add('approve', $blocker);
        }
    }

    /**
     * @return array<int, string>
     */
    private function blockers(): array
    {
        $supplier = $this->supplierChangeRequest->supplier;

        if ($this->supplierChangeRequest->type === SupplierChangeRequestTypeEnum::MERGE) {
            return MergeSuppliers::make()->blockers($supplier, $this->supplierChangeRequest->targetSupplier);
        }

        $openOrders = DeactivateSupplier::make()->openPurchaseOrderReferences($supplier);

        return $openOrders->isNotEmpty()
            ? [__('The supplier still has open purchase orders: :references', ['references' => $openOrders->implode(', ')])]
            : [];
    }

    /**
     * An approval deactivates or merges in a queued job, which can take minutes for a supplier with many products;
     * the dialog follows it through the progress cache. Keeping the supplier is quick and runs at once.
     */
    public function asController(SupplierChangeRequest $supplierChangeRequest, ActionRequest $request): SupplierChangeRequest
    {
        $this->supplierChangeRequest = $supplierChangeRequest;
        $this->initialisationFromGroup($supplierChangeRequest->group, $request);

        $modelData = [...Arr::except($this->validatedData, 'decided_by_user_id'), 'decided_by_user_id' => $request->user()->id];

        if (!$modelData['approve']) {
            return $this->handle($supplierChangeRequest, $modelData);
        }

        self::putProgress($supplierChangeRequest, 'queued');
        self::dispatch($supplierChangeRequest, $modelData);

        return $supplierChangeRequest;
    }

    public function asJob(SupplierChangeRequest $supplierChangeRequest, array $modelData): void
    {
        if ($supplierChangeRequest->refresh()->state !== SupplierChangeRequestStateEnum::PENDING) {
            self::putProgress($supplierChangeRequest, 'failed', message: __('This suggestion was already decided'));

            return;
        }

        try {
            $this->handle($supplierChangeRequest, $modelData);
        } catch (ValidationException $exception) {
            self::putProgress($supplierChangeRequest, 'failed', message: collect($exception->errors())->flatten()->implode(' '));
        } catch (Throwable $exception) {
            self::putProgress($supplierChangeRequest, 'failed', message: __('The approval failed: :error', ['error' => $exception->getMessage()]));
            report($exception);
        }
    }

    public function action(SupplierChangeRequest $supplierChangeRequest, array $modelData): SupplierChangeRequest
    {
        $this->asAction              = true;
        $this->supplierChangeRequest = $supplierChangeRequest;
        $this->initialisationFromGroup($supplierChangeRequest->group, $modelData);

        return $this->handle($supplierChangeRequest, $this->validatedData);
    }

    public function jobFailed(Throwable $exception, SupplierChangeRequest $supplierChangeRequest, array $modelData): void
    {
        self::putProgress($supplierChangeRequest, 'failed', message: __('The approval stopped before finishing: :error', ['error' => $exception->getMessage()]));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
