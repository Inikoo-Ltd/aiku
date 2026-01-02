<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOutboxes;
use App\Actions\Comms\PostRoom\Hydrators\PostRoomHydrateOutboxes;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOutboxes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOutboxes;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Comms\Outbox\OutboxSubTypeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Validation\Rule;

class StoreOutbox extends OrgAction
{
    use WithNoStrictRules;

    public function handle(OrgPostRoom $orgPostRoom, Organisation|Shop|Website|Fulfilment $parent, array $modelData): Outbox
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'post_room_id', $orgPostRoom->post_room_id);
        data_set($modelData, 'organisation_id', $orgPostRoom->organisation_id);


        if ($parent instanceof Shop) {
            data_set($modelData, 'shop_id', $parent->id);
            if ($parent->type == ShopTypeEnum::FULFILMENT) {
                data_set($modelData, 'fulfilment_id', $parent->fulfilment->id);
            }
        } elseif ($parent instanceof Website) {
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'website_id', $parent->id);
        } elseif ($parent instanceof Fulfilment) {
            data_set($modelData, 'shop_id', $parent->shop_id);
            data_set($modelData, 'fulfilment_id', $parent->id);
        }

        if (in_array($modelData['code'], [
            OutboxCodeEnum::REORDER_REMINDER,
            OutboxCodeEnum::REORDER_REMINDER_2ND,
            OutboxCodeEnum::REORDER_REMINDER_3RD
        ])) {
            $modelData['sub_type'] = OutboxSubTypeEnum::REORDER_REMINDER;
        }


        /** @var Outbox $outbox */
        $outbox = $orgPostRoom->outboxes()->create($modelData);
        $outbox->stats()->create();
        $outbox->intervals()->create();
        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $outbox->timeSeries()->create(['frequency' => $frequency]);
        }


        GroupHydrateOutboxes::dispatch($outbox->group);
        OrganisationHydrateOutboxes::dispatch($outbox->organisation);
        if ($outbox->shop_id) {
            ShopHydrateOutboxes::dispatch($outbox->shop);
        }
        PostRoomHydrateOutboxes::dispatch($outbox->postRoom);

        return $outbox;
    }


    public function rules(): array
    {
        $rules = [
            'code'       => ['required', Rule::enum(OutboxCodeEnum::class)],
            'type'       => ['required', Rule::enum(OutboxTypeEnum::class)],
            'state'      => ['required', Rule::enum(OutboxStateEnum::class)],
            'builder'    => ['nullable', Rule::enum(OutboxBuilderEnum::class)],
            'name'       => ['required', 'max:250', 'string'],
            'model_type' => ['nullable', Rule::in('Mailshot', 'EmailOngoingRun', 'EmailBulkRun')],


        ];
        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(OrgPostRoom $orgPostRoom, Organisation|Shop|Website|Fulfilment $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Outbox
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($orgPostRoom->organisation, $modelData);

        return $this->handle($orgPostRoom, $parent, $this->validatedData);
    }
}
