<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\OrgAction;
use App\Models\Dispatching\BatchCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteBatchCode extends OrgAction
{
    public function handle(BatchCode $batchCode): void
    {
        $batchCode->deliveryNoteItems()->update(['batch_code_id' => null]);
        $batchCode->delete();
    }

    public function asController(BatchCode $batchCode, ActionRequest $request): void
    {
        $this->initialisation($batchCode->organisation, $request);

        $this->handle($batchCode);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
