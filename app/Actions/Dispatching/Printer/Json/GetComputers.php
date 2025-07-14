<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 22:51:13 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Printer\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\WithPrintNode;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Rawilk\Printing\Api\PrintNode\Resources\Computer;

class GetComputers extends OrgAction
{
    use WithPrintNode;


    public function handle(): Collection
    {
        $this->ensureClientInitialized();

        $options = [
            'limit' => 50,
            'dir' => 'desc',
        ];

        if ($after = $this->get('after')) {
            $options['after'] = $after;
        }

        return Computer::all(
            $options
        );
    }

    public function jsonResponse(Collection $computers): Collection
    {
        return $computers;
    }

    public function rules(): array
    {
        return [
            'after' => ['sometimes', 'integer'],
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
}
