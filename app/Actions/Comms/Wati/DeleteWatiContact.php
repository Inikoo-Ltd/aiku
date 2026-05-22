<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Thursday, 22 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati;

use App\Actions\OrgAction;
use App\Models\Comms\WatiContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWatiContact extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(WatiContact $watiContact): void
    {
        if (!$watiContact->isLinked()) {
            throw ValidationException::withMessages([
                'contact' => 'Only linked contacts can be deleted from the local database.',
            ]);
        }

        $watiContact->delete();
    }

    public function asController(WatiContact $watiContact, ActionRequest $request): void
    {
        $this->initialisation($watiContact->shop->organisation, $request);
        $this->handle($watiContact);
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Contact removed from local database.']);
    }
}
