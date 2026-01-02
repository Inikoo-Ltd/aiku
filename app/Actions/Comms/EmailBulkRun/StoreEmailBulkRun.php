<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 13:30:40 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailBulkRun;

use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateEmailBulkRuns;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateEmailsBulkRuns;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunTypeEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailOngoingRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreEmailBulkRun extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithNoStrictRules;


    private EmailOngoingRun $emailOngoingRun;

    /**
     * @throws \Throwable
     */
    public function handle(EmailOngoingRun $emailOngoingRun, array $modelData): EmailBulkRun
    {
        data_set($modelData, 'group_id', $emailOngoingRun->group_id);
        data_set($modelData, 'organisation_id', $emailOngoingRun->organisation_id);
        data_set($modelData, 'shop_id', $emailOngoingRun->shop_id);
        data_set($modelData, 'outbox_id', $emailOngoingRun->outbox_id);
        data_set($modelData, 'email_id', $emailOngoingRun->email_id);
        data_set($modelData, 'snapshot_id', $emailOngoingRun->email->live_snapshot_id, overwrite: false);


        $emailBulkRun = DB::transaction(function () use ($emailOngoingRun, $modelData) {
            /** @var EmailBulkRun $emailRun */
            $emailRun = $emailOngoingRun->emailBulkRuns()->create($modelData);
            $emailRun->stats()->create();
            $emailRun->intervals()->create();

            return $emailRun;
        });

        $outbox = $emailOngoingRun->outbox;

        OutboxHydrateEmailBulkRuns::dispatch($outbox)->delay($this->hydratorsDelay);
        GroupHydrateEmailsBulkRuns::dispatch($emailOngoingRun->group_id)->delay($this->hydratorsDelay);

        return $emailBulkRun;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }


    public function rules(): array
    {
        $rules = [
            'subject' => ['required', 'string', 'max:255'],
            'type'    => ['sometimes', 'required', Rule::enum(EmailBulkRunTypeEnum::class)],
            'state'   => ['required', Rule::enum(EmailBulkRunStateEnum::class)],
        ];

        if (!$this->strict) {
            $rules['scheduled_at']     = ['nullable', 'date'];
            $rules['start_sending_at'] = ['nullable', 'date'];
            $rules['sent_at']          = ['nullable', 'date'];
            $rules['stopped_at']       = ['nullable', 'date'];
            $rules['snapshot_id']      = [
                'sometimes',
                'required',
                Rule::exists('snapshots', 'id')
                    ->where('parent_type', 'Email')
                    ->where('parent_id', $this->emailOngoingRun->email_id)
            ];


            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function action(EmailOngoingRun $emailOngoingRun, array $modelData, int $hydratorsDelay = 0, bool $strict = true): EmailBulkRun
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->emailOngoingRun = $emailOngoingRun;
        $this->initialisationFromShop($emailOngoingRun->shop, $modelData);

        return $this->handle($emailOngoingRun, $this->validatedData);
    }


}
