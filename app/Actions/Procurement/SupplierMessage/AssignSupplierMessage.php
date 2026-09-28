<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage;

use App\Actions\OrgAction;
use App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class AssignSupplierMessage extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * The whole thread moves together, and the sender's address is remembered through it: the
     * router reads past assignments, so the next mail from that address finds its supplier alone.
     */
    public function handle(SupplierMessage $supplierMessage, OrgSupplier|OrgAgent|OrgPartner $counterpart): int
    {
        return SupplierMessage::where('organisation_id', $supplierMessage->organisation_id)
            ->when(
                $supplierMessage->gmail_thread_id,
                fn ($query) => $query->where('gmail_thread_id', $supplierMessage->gmail_thread_id),
                fn ($query) => $query->where('id', $supplierMessage->id)
            )
            ->update([
                ...SupplierMessage::counterpartAttributes($counterpart),
                'routed_by'       => SupplierMessageRoutedByEnum::MANUAL,
                'updated_at'      => now(),
            ]);
    }

    public static function findCounterpart(Organisation $organisation, string $counterpart): OrgSupplier|OrgAgent|OrgPartner
    {
        [$type, $id] = explode(':', $counterpart);

        $model = match ($type) {
            'supplier' => OrgSupplier::class,
            'agent'    => OrgAgent::class,
            'partner'  => OrgPartner::class,
        };

        return $model::where('organisation_id', $organisation->id)->findOrFail($id);
    }

    public function rules(): array
    {
        return [
            'counterpart' => ['required', 'string', 'regex:/^(supplier|agent|partner):\d+$/'],
        ];
    }

    public function asController(Organisation $organisation, SupplierMessage $supplierMessage, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierMessage->organisation_id === $organisation->id, 404);

        $this->handle($supplierMessage, self::findCounterpart($organisation, $this->validatedData['counterpart']));

        return back();
    }
}
