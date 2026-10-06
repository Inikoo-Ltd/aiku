<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Imports\Procurement;

use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItem;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PartnerShoppingListItemImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    public function __construct(protected OrgPartner $orgPartner, Upload $upload)
    {
        $this->upload = $upload;
    }

    /**
     * Each row sets the quantity, in SKOs, of one of our SKO codes on the open shopping list, going
     * through the same action as the page, so whole production batches still apply.
     */
    public function storeModel($row, $uploadRecord): void
    {
        $code     = trim((string) $row->get('code'));
        $orgStock = $code === '' ? null : OrgStock::whereIn('organisation_id', [$this->orgPartner->organisation_id, $this->orgPartner->partner_id])
            ->whereRaw('lower(code) = lower(?)', [$code])
            ->orderByRaw('organisation_id = ? desc', [$this->orgPartner->organisation_id])
            ->orderByRaw("state = '".OrgStockStateEnum::ACTIVE->value."' desc")
            ->first();

        if (!$orgStock) {
            $this->setRecordAsFailed($uploadRecord, [__('SKO :code not found', ['code' => $code])]);

            return;
        }

        try {
            StorePartnerShoppingListItem::make()->action($this->orgPartner, $orgStock, ['quantity' => (float) $row->get('quantity')]);
            $this->setRecordAsCompleted($uploadRecord);
        } catch (ValidationException $e) {
            $this->setRecordAsFailed($uploadRecord, collect($e->errors())->flatten()->all());
        } catch (HttpException $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        } catch (Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'code'     => ['required', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
