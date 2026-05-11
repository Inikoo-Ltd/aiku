<?php

namespace App\Actions\Fulfilment\PickingSession\Json;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPalletReturnsForPickingSession extends OrgAction
{
    use AsAction;

    public function handle(PickingSession $pickingSession, string $mode, ?string $returnType = null): array
    {
        $normalizedReturnType = is_string($returnType)
            ? str_replace('-', '_', strtolower($returnType))
            : null;

        if ($mode === 'remove') {
            $query = $pickingSession->palletReturns();
        } else {
            $query = PalletReturn::query()
                ->where('warehouse_id', $pickingSession->warehouse_id)
                ->whereIn('state', [
                    PalletReturnStateEnum::CONFIRMED->value,
                    PalletReturnStateEnum::SUBMITTED->value,
                ])
                ->whereDoesntHave('pickingSessions');
        }

        if (is_string($normalizedReturnType) && $normalizedReturnType !== '') {
            $query->where('type', $normalizedReturnType);
        }

        return $query
            ->withCount('items')
            ->get()
            ->map(fn (PalletReturn $palletReturn) => [
                'id'          => $palletReturn->id,
                'created_at'  => $palletReturn->created_at,
                'reference'   => $palletReturn->reference,
                'state_label' => PalletReturnStateEnum::labels()[$palletReturn->state->value] ?? 'N/A',
                'number_items' => $palletReturn->items_count,
                'type'        => $palletReturn->type?->value,
            ])
            ->values()
            ->all();
    }

    public function asController(PickingSession $pickingSession, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        $mode = $request->query('mode', 'add');
        $returnType = $request->query('return_type');

        return response()->json($this->handle($pickingSession, $mode, is_string($returnType) ? $returnType : null));
    }
}
