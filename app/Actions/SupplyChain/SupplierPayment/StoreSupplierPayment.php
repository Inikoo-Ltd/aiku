<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 07:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPayment;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\SupplierPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreSupplierPayment extends OrgAction
{
    private StockDelivery $stockDelivery;

    /**
     * @param  array{date: string, amount: float|string, method: string, reference?: string|null, bank_statement?: UploadedFile|null}  $modelData
     */
    public function handle(StockDelivery $stockDelivery, array $modelData): SupplierPayment
    {
        $file  = Arr::get($modelData, 'bank_statement');
        $media = $file ? SaveModelAttachment::make()->action($stockDelivery, [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'scope'        => PurchaseOrderAttachmentScopeEnum::BANK_STATEMENT->value,
            'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]) : null;

        return $stockDelivery->supplierPayments()->create([
            'group_id'        => $stockDelivery->group_id,
            'organisation_id' => $stockDelivery->organisation_id,
            'currency_id'     => $stockDelivery->supplierInvoice?->currency_id ?? $stockDelivery->currency_id,
            'date'            => $modelData['date'],
            'amount'          => round((float) $modelData['amount'], 2),
            'method'          => $modelData['method'],
            'reference'       => Arr::get($modelData, 'reference'),
            'media_id'        => $media?->id,
            'created_by'      => $this->asAction ? null : request()->user()?->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'date'           => ['required', 'date', 'before_or_equal:+1 year'],
            'amount'         => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'method'         => ['required', Rule::enum(PurchaseOrderPaymentMethodEnum::class)],
            'reference'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_statement' => ['required_unless:method,cash', 'nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && CheckAgainstAgreedTerms::hasUnaccepted(CheckAgainstAgreedTerms::make()->stockDelivery($this->stockDelivery))) {
            $validator->errors()->add('agreed_terms', __('The invoice charges more than was agreed. Purchasing must accept each difference with a reason before it is paid.'));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user()->authTo(["procurement.{$this->organisation->id}.edit", "accounting.{$this->organisation->id}.edit"]);
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): SupplierPayment
    {
        abort_if($stockDelivery->agent_id || $stockDelivery->parent_type !== 'OrgSupplier', 404);
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): SupplierPayment
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
