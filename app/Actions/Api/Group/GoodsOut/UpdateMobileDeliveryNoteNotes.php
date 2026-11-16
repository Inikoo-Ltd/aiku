<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileDeliveryNoteNotes
{
    use AsController;
    use WithMobilePickingGuards;

    private const array FIELDS = [
        'warehouse' => 'private_warehouse_note',
        'shipping'  => 'shipping_notes',
    ];

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanView($deliveryNote, $user);

        $data = Validator::make($request->all(), [
            'field' => ['required', Rule::in(array_keys(self::FIELDS))],
            'text'  => ['present', 'nullable', 'string', 'max:4000'],
        ])->validate();

        $field      = self::FIELDS[$data['field']];
        $noteBox    = collect(ShowDeliveryNote::make()->getDeliveryNoteNotes($deliveryNote)['note_list'])->firstWhere('field', $field);
        abort_unless((bool) ($noteBox['editable'] ?? false), 403, __('You can not change the notes of this delivery note.'));
        $this->ensureNotLockedInAurora($deliveryNote);

        UpdateDeliveryNote::make()->action($deliveryNote, [$field => $data['text']]);

        return $this->respondWith($deliveryNote, $user, 'done', __('Note saved.'));
    }
}
