<?php

/*
 * Author: Andi Ferdiawan
 * Created: Mon, 14 Jul 2026 13:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNoteLeaflet;

use App\Actions\OrgAction;
use App\Actions\Traits\WithPrintNode;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Rawilk\Printing\Api\PrintNode\Resources\PrintJob;

class PrintDeliveryNoteLeaflet extends OrgAction
{
    use WithPrintNode;
    use WithPrintDeliveryNoteLeaflet;

    public function handle(DeliveryNoteLeaflet $deliveryNoteLeaflet): PrintJob|RedirectResponse
    {
        if (!$deliveryNoteLeaflet->media) {
            throw ValidationException::withMessages([
                'messages' => __('This insert has no uploaded file to print.'),
            ]);
        }

        try {
            return $this->printDeliveryNoteLeaflet($deliveryNoteLeaflet, $this->get('printerId'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error printing delivery note leaflet: '.$e->getMessage());
            throw ValidationException::withMessages([
                'messages' => __('Error printing leaflet'),
            ]);
        }
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

    public function asController(DeliveryNoteLeaflet $deliveryNoteLeaflet, ActionRequest $request): PrintJob|RedirectResponse
    {
        return $this->handle($deliveryNoteLeaflet);
    }

    public function jsonResponse(PrintJob|RedirectResponse $result): PrintJob|RedirectResponse
    {
        return $result;
    }
}
