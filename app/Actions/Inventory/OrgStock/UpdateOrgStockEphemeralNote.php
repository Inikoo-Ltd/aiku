<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgStockEphemeralNote extends OrgAction
{
    public const array EXPIRY_DAYS = [7, 14, 30];

    /**
     * @param array{note?: string|null, expires_in_days?: int} $modelData
     */
    public function handle(OrgStock $orgStock, array $modelData, ?User $author = null): OrgStock
    {
        $note = trim((string) ($modelData['note'] ?? ''));

        if ($note === '') {
            $orgStock->update([
                'ephemeral_note'            => null,
                'ephemeral_note_user_id'    => null,
                'ephemeral_note_set_at'     => null,
                'ephemeral_note_expires_at' => null,
            ]);

            return $orgStock;
        }

        $orgStock->update([
            'ephemeral_note'            => $note,
            'ephemeral_note_user_id'    => $author?->id,
            'ephemeral_note_set_at'     => now(),
            'ephemeral_note_expires_at' => now()->addDays((int) ($modelData['expires_in_days'] ?? 30)),
        ]);

        return $orgStock;
    }

    /**
     * @return array{text: string, author: string|null, set_at: string|null, expires_at: string}|null
     */
    public static function present(OrgStock $orgStock): ?array
    {
        if (!$orgStock->ephemeral_note || !$orgStock->ephemeral_note_expires_at || $orgStock->ephemeral_note_expires_at->isPast()) {
            return null;
        }

        return [
            'text'       => $orgStock->ephemeral_note,
            'author'     => $orgStock->ephemeral_note_user_id ? User::find($orgStock->ephemeral_note_user_id)?->getPasskeyDisplayName() : null,
            'set_at'     => $orgStock->ephemeral_note_set_at?->toIso8601String(),
            'expires_at' => $orgStock->ephemeral_note_expires_at->toIso8601String(),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation));
    }

    public function rules(): array
    {
        return [
            'note'            => ['present', 'nullable', 'string', 'max:500'],
            'expires_in_days' => ['sometimes', 'integer', 'in:'.implode(',', self::EXPIRY_DAYS)],
        ];
    }

    public function action(OrgStock $orgStock, array $modelData, ?User $author = null): OrgStock
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData, $author);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
