<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 13:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNoteLeaflet;

use App\Actions\OrgAction;
use App\Actions\Traits\WithPrintNode;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class PrintDeliveryNoteLeaflets extends OrgAction
{
    use WithPrintNode;
    use WithPrintDeliveryNoteLeaflet;

    public function handle(DeliveryNote $deliveryNote): RedirectResponse
    {
        $printerId = $this->get('printerId');
        $printed   = 0;

        try {
            foreach ($deliveryNote->leaflets()->whereNotNull('media_id')->get() as $leaflet) {
                if ($this->printDeliveryNoteLeaflet($leaflet, $printerId)) {
                    $printed++;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error printing delivery note leaflets: '.$e->getMessage());
            throw ValidationException::withMessages([
                'messages' => __('Error printing leaflets'),
            ]);
        }

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => trans_choice('{0} No inserts to print.|{1} :count insert sent to printer.|[2,*] :count inserts sent to printer.', $printed, ['count' => $printed]),
        ]);
    }

    public function afterValidator(Validator $validator): void
    {
        $user      = request()->user();
        $printerId = Arr::get($user->settings, 'preferred_printer_id');
        if (!$printerId) {
            throw ValidationException::withMessages([
                'messages' => __('You must set a preferred printer in your user settings!'),
            ]);
        }

        $printByPrintNode = Arr::get($user->group->settings, 'printnode.print_by_printnode', false);
        if (!$printByPrintNode) {
            throw ValidationException::withMessages([
                'messages' => __('Print by Printnode is not enabled for your group!'),
            ]);
        }

        $this->set('printerId', $printerId);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): RedirectResponse
    {
        return $this->handle($deliveryNote);
    }
}
