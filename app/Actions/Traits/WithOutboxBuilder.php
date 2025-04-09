<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Dec 2024 22:58:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Comms\Email\StoreEmail;
use App\Actions\Comms\EmailOngoingRun\StoreEmailOngoingRun;
use App\Actions\Comms\EmailOngoingRun\UpdateEmailOngoingRun;
use App\Actions\Comms\Outbox\UpdateOutbox;
use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunStatusEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithOutboxBuilder
{
    public function getDefaultBuilder(OutboxCodeEnum $case, Organisation|Shop|Fulfilment|Website $model): ?OutboxBuilderEnum
    {
        $builder = $case->defaultBuilder();
        if (!$builder and $case != OutboxCodeEnum::TEST) {
            $builder = Arr::get(
                $model->group->settings,
                'default_outbox_builder',
                config('app.default_outbox_builder')
            );
        }

        if (is_string($builder)) {
            match ($builder) {
                'unlayer' => $builder = OutboxBuilderEnum::UNLAYER,
                'beefree' => $builder = OutboxBuilderEnum::BEEFREE,
                default => null
            };
        }

        return $builder;
    }

    /**
     * @throws \Throwable
     */
    public function setEmailOngoingRuns(Outbox $outbox, OutboxCodeEnum $case, Organisation|Shop|Fulfilment|Website $model): void
    {
        if ($outbox->model_type == 'EmailOngoingRun') {
            if (!$outbox->emailOngoingRun) {
                $emailOngoingRun = StoreEmailOngoingRun::make()->action(
                    $outbox,
                    [
                        'type' => $case->emailOngoingRunType(),
                    ]
                );
            } else {
                $emailOngoingRun = $outbox->emailOngoingRun;
            }


            $emailTemplate = EmailTemplate::where('state', EmailTemplateStateEnum::ACTIVE)
                ->whereJsonContains('data->outboxes', $outbox->code)->first();


            if ($emailTemplate) {
                if ($emailOngoingRun->email) {
                    if ($emailOngoingRun->email->builder == EmailBuilderEnum::BLADE) {
                        if ($emailOngoingRun->email->liveSnapshot->layout != $emailTemplate->layout) {
                            $liveSnapShot = DB::transaction(function () use ($emailOngoingRun, $emailTemplate) {
                                $currentSnapshot = $emailOngoingRun->email->liveSnapshot;

                                $snapshotData = [
                                    'builder'      => $emailTemplate->builder->value,
                                    'layout'       => $emailTemplate->layout,
                                    'first_commit' => true,
                                    'recyclable'   => false,
                                    'state'        => SnapshotStateEnum::LIVE,
                                    'published_at' => now()
                                ];
                                $liveSnapShot = StoreEmailSnapshot::make()->action(
                                    $emailOngoingRun->email,
                                    $snapshotData,
                                    strict: false
                                );


                                UpdateSnapshot::make()->action(
                                    $currentSnapshot,
                                    [
                                        'state'           => SnapshotStateEnum::HISTORIC,
                                        'published_until' => now()
                                    ]
                                );

                                return $liveSnapShot;
                            });


                            $emailOngoingRun->email->update(
                                [
                                    'live_snapshot_id' => $liveSnapShot->id,
                                ]
                            );
                        }
                    }
                } else {
                    $email = StoreEmail::make()->action(
                        $emailOngoingRun,
                        $emailTemplate,
                        modelData: [
                            'subject'               => $case->label(),
                            'snapshot_state'        => SnapshotStateEnum::LIVE,
                            'snapshot_published_at' => $model->created_at,
                            'snapshot_recyclable'   => false,
                            'snapshot_first_commit' => true,
                            'builder'               => match ($this->getDefaultBuilder($case, $model)) {
                                OutboxBuilderEnum::UNLAYER => EmailBuilderEnum::UNLAYER,
                                OutboxBuilderEnum::BEEFREE => EmailBuilderEnum::BEEFREE,
                                OutboxBuilderEnum::BLADE => EmailBuilderEnum::BLADE,
                                default => null
                            }
                        ],
                        strict: false
                    );

                    UpdateEmailOngoingRun::make()->action(
                        $emailOngoingRun,
                        [
                            'email_id' => $email->id,
                            'status'   => EmailOngoingRunStatusEnum::ACTIVE
                        ]
                    );

                    UpdateOutbox::make()->action(
                        $outbox,
                        [
                            'state'    => OutboxStateEnum::ACTIVE,
                            'model_id' => $emailOngoingRun->id
                        ]
                    );
                }
            }
        }
    }

}
