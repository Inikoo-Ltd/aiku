<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StorePartnerShoppingListItems extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @param array<int, array{org_stock_id: int, quantity: float, notes?: string}> $lines
     *
     * @return array{created: int, skipped: array<int, array{org_stock_id: int, reason: string}>}
     */
    public function handle(OrgPartner $orgPartner, array $lines): array
    {
        $created = 0;
        $skipped = [];
        foreach ($lines as $line) {
            $orgStock = OrgStock::find($line['org_stock_id']);
            if (!$orgStock) {
                continue;
            }

            try {
                StorePartnerShoppingListItem::make()->action($orgPartner, $orgStock, [
                    'quantity' => $line['quantity'],
                    'notes'    => $line['notes'] ?? null,
                ]);
                $created++;
            } catch (HttpException $exception) {
                $skipped[] = [
                    'org_stock_id' => $orgStock->id,
                    'reason'       => $exception->getMessage(),
                ];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function rules(): array
    {
        return [
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.org_stock_id' => ['required', 'integer', 'exists:org_stocks,id'],
            'lines.*.quantity'     => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes'        => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->validatedData['lines']);
    }

    public function action(OrgPartner $orgPartner, array $lines): array
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, ['lines' => $lines]);

        return $this->handle($orgPartner, $this->validatedData['lines']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
