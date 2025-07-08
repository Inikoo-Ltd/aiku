<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 22:51:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Printer\Json;

use App\Actions\Dispatching\Printer\WithPrintNode;
use App\Actions\OrgAction;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Rawilk\Printing\Api\PrintNode\Resources\Printer;

class GetPrinters extends OrgAction
{
    use WithPrintNode;


    public function handle(): Collection
    {
        $this->ensureClientInitialized();
        return Printer::all();
    }

    public function jsonResponse(Collection $printers): Collection
    {
        return $printers;
    }

    public function rules(): array
    {
        return [
            'after' => ['nullable', 'integer'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('after', $request->get('after'));
    }



    public function asController(ActionRequest $request): Collection
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }

    public function action(array $modelData): Collection
    {
        $this->asAction = true;
        $this->initialisationFromGroup(group(), $modelData);

        return $this->handle();
    }
}
