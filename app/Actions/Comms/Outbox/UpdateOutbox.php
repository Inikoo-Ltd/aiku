<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Mail\OutboxResource;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateOutbox extends OrgAction
{
    use WithActionUpdate;

    private Outbox $outbox;

    public function handle(Outbox $outbox, array $modelData): Outbox
    {
        return $this->update($outbox, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->hasPermissionTo("mail.edit");
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                new IUnique(
                    table: 'outboxes',
                    extraConditions: [
                        ['column' => 'organisation_id', 'value' => $this->organisation->id],
                        ['column' => 'id', 'value' => $this->outbox->id, 'operator' => '!=']
                    ]
                ),
                'string',
                'max:255'
            ],
        ];
    }

    public function action(Outbox $outbox, array $modelData): Outbox
    {
        $this->asAction = true;
        $this->outbox   = $outbox;
        $this->initialisation($outbox->organisation, $modelData);

        return $this->handle($outbox, $this->validatedData);
    }


    public function asController(Organisation $organisation, Outbox $outbox, ActionRequest $request): Outbox
    {
        $this->outbox = $outbox;
        $this->initialisation($organisation, $request);

        return $this->handle($outbox, $this->validatedData);
    }


    public function jsonResponse(Outbox $outbox): OutboxResource
    {
        return new OutboxResource($outbox);
    }
}
