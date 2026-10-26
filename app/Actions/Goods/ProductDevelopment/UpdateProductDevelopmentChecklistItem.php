<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-21h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\OrgAction;
use App\Actions\Procurement\ProcurementNote\StoreProcurementNote;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistItemStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductDevelopmentChecklistItem extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string SAVE = 'save';
    public const string NOT_APPLICABLE = 'not_applicable';
    public const string REOPEN = 'reopen';

    public const string CHARGE_PREFIX = 'charge_';

    private ?ProductDevelopmentChecklistItem $checklistItem = null;

    private function target(): ProductDevelopmentChecklistItem
    {
        return $this->checklistItem ?? request()->route('productDevelopmentChecklistItem');
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $checklistItem = $this->target();

        return $this->canViewProductDevelopment($request->user(), $checklistItem->productDevelopment)
            && $this->productDevelopmentAccess()->canEditChecklistItem($request->user(), $checklistItem);
    }

    /**
     * @throws ValidationException
     */
    public function handle(ProductDevelopmentChecklistItem $checklistItem, User $user, array $modelData): ProductDevelopmentChecklistItem
    {
        $operation = $modelData['operation'];

        DB::transaction(function () use ($checklistItem, $user, $modelData, $operation) {
            if ($operation === self::REOPEN) {
                $checklistItem->update([
                    'state'           => ProductDevelopmentChecklistItemStateEnum::MISSING,
                    'na_reason'       => null,
                    'completed_by_id' => null,
                    'completed_at'    => null,
                ]);

                return;
            }

            if ($operation === self::NOT_APPLICABLE) {
                $checklistItem->update([
                    'state'           => ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE,
                    'na_reason'       => trim($modelData['na_reason']),
                    'completed_by_id' => $user->id,
                    'completed_at'    => now(),
                ]);

                return;
            }

            $targetField = ProductDevelopmentTargetFieldEnum::tryFrom((string) $checklistItem->target_field);
            $value       = Arr::get($modelData, 'value');

            if ($targetField) {
                $this->writeTargetField($checklistItem, $targetField, $value);
            }

            $checklistItem->update([
                'state'           => ProductDevelopmentChecklistItemStateEnum::COMPLETE,
                'value'           => $targetField ? null : ['text' => trim((string) $value)],
                'na_reason'       => null,
                'completed_by_id' => $user->id,
                'completed_at'    => now(),
            ]);

            if (Str::startsWith($checklistItem->key, self::CHARGE_PREFIX)) {
                $this->postChargeToSupplierNotes($checklistItem, $user, (string) $value);
            }
        });

        ProductDevelopmentHydrateChecklist::run($checklistItem->productDevelopment);
        CreateProductDevelopmentTask::make()->syncProgress($checklistItem->productDevelopment, $user);

        return $checklistItem->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function writeTargetField(ProductDevelopmentChecklistItem $checklistItem, ProductDevelopmentTargetFieldEnum $targetField, mixed $value): void
    {
        $item = $checklistItem->item;
        if (!$item) {
            throw ValidationException::withMessages(['value' => __('This item is not tied to a SKU')]);
        }

        if ($targetField === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_INGREDIENTS) {
            try {
                UpdateTradeUnit::make()->action($item->tradeUnit, ['ingredients' => array_values(array_filter((array) $value, 'is_string'))]);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['value' => collect($exception->errors())->flatten()->first() ?? __('This value cannot be saved')]);
            }

            return;
        }

        if ($targetField === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_MATERIALS) {
            $materials = collect(is_array($value) ? $value : [])
                ->filter(fn ($line) => is_array($line) && trim((string) ($line['material'] ?? '')) !== '')
                ->map(fn (array $line) => ['material' => trim((string) $line['material']), 'percentage' => isset($line['percentage']) && $line['percentage'] !== '' && $line['percentage'] !== null ? (float) $line['percentage'] : null])
                ->values();
            if ($materials->isEmpty()) {
                throw ValidationException::withMessages(['value' => __('Add at least one material')]);
            }
            if ($materials->contains(fn (array $line) => $line['percentage'] !== null && ($line['percentage'] < 0 || $line['percentage'] > 100)) || $materials->sum('percentage') > 100.001) {
                throw ValidationException::withMessages(['value' => __('The percentages must add up to 100% at most')]);
            }

            $tradeUnit  = $item->tradeUnit;
            $compliance = $tradeUnit->compliance ?? [];
            data_set($compliance, 'material_composition', [
                'text'      => $materials->map(fn (array $line) => trim($line['material'].($line['percentage'] !== null ? ' '.rtrim(rtrim(number_format($line['percentage'], 2, '.', ''), '0'), '.').'%' : '')))->implode(', '),
                'materials' => $materials->all(),
            ]);
            UpdateTradeUnit::make()->action($tradeUnit, ['compliance' => $compliance]);

            return;
        }

        if ($targetField->isConfirmedBySaving()) {
            $allowed = $targetField === ProductDevelopmentTargetFieldEnum::TRADE_UNIT_PICTOGRAMS
                ? array_keys(ProductDevelopmentTargetFieldEnum::pictograms())
                : ProductDevelopmentTargetFieldEnum::labelMarkKeys();

            try {
                UpdateTradeUnit::make()->action($item->tradeUnit, Arr::only(is_array($value) ? $value : [], $allowed));
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['value' => collect($exception->errors())->flatten()->first() ?? __('This value cannot be saved')]);
            }

            return;
        }

        $value = match ($targetField->inputType()) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'country' => (int) $value,
            'dimensions' => is_array($value) ? ['type' => 'rectangular', 'units' => 'cm', ...Arr::only($value, ['h', 'l', 'w', 'type', 'units'])] : $value,
            default => is_string($value) ? trim($value) : $value,
        };

        try {
            if ($targetField->isOnSupplierProduct()) {
                if (!$item->supplierProduct) {
                    throw ValidationException::withMessages(['value' => __('Link a supplier product to :sku first', ['sku' => $item->tradeUnit->code])]);
                }
                UpdateSupplierProduct::make()->action($item->supplierProduct, [$targetField->column() => $value]);
            } else {
                UpdateTradeUnit::make()->action($item->tradeUnit, [$targetField->column() => $value]);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['value' => collect($exception->errors())->flatten()->first() ?? __('This value cannot be saved')]);
        }
    }

    private function postChargeToSupplierNotes(ProductDevelopmentChecklistItem $checklistItem, User $user, string $value): void
    {
        $productDevelopment = $checklistItem->productDevelopment;
        if (!$productDevelopment->supplier || trim($value) === '') {
            return;
        }

        StoreProcurementNote::make()->action($productDevelopment->supplier, [
            'category'       => 'charges',
            'title'          => $productDevelopment->code.' · '.$checklistItem->label,
            'body'           => trim($value)."\n\n".__('Agreed in product development :code (:name) by :user', [
                'code' => $productDevelopment->code,
                'name' => $productDevelopment->name,
                'user' => $user->contact_name ?: $user->username,
            ]),
            'author_user_id' => $user->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in([self::SAVE, self::NOT_APPLICABLE, self::REOPEN])],
            'value'     => ['nullable'],
            'na_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $operation = $this->get('operation');

        if ($operation === self::NOT_APPLICABLE && trim((string) $this->get('na_reason')) === '') {
            $validator->errors()->add('na_reason', __('Say why this does not apply'));
        }

        if ($operation === self::SAVE && $this->target()->key === UploadProductDevelopmentFactoryPhoto::KEY) {
            $validator->errors()->add('value', __('Upload a photo for each angle'));

            return;
        }

        if ($operation === self::SAVE) {
            $value = $this->get('value');
            $empty = $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && collect($value)->only(['h', 'l', 'w'])->filter(fn ($measure) => (float) $measure > 0)->isEmpty() && array_key_exists('h', $value));
            if ($empty) {
                $validator->errors()->add('value', __('Enter a value, or mark it as not applicable'));
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function asController(ProductDevelopmentChecklistItem $productDevelopmentChecklistItem, ActionRequest $request): ProductDevelopmentChecklistItem
    {
        $this->checklistItem = $productDevelopmentChecklistItem;
        $this->initialisationFromGroup($productDevelopmentChecklistItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentChecklistItem, $request->user(), $this->validatedData);
    }

    /**
     * @throws ValidationException
     */
    public function action(ProductDevelopmentChecklistItem $checklistItem, User $user, array $modelData): ProductDevelopmentChecklistItem
    {
        $this->asAction      = true;
        $this->checklistItem = $checklistItem;
        $this->initialisationFromGroup($checklistItem->productDevelopment->group, $modelData);

        return $this->handle($checklistItem, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
