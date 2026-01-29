<?php

namespace App\Actions\Helpers\SalesChannel;

use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Ordering\SalesChannel;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSalesChannelOptions
{
    use AsObject;

    public function handle($salesChannels): array
    {
        $selectOptions = [];
        /** @var SalesChannel $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $selectOptions[$salesChannel->id] = [
                'name' => $salesChannel->name,
                'id'   => $salesChannel->id,
                'code' => $salesChannel->code,
            ];
        }

        return $selectOptions;
    }

    public function all(): array
    {
        return $this->handle(SalesChannel::all());
    }

    public function getStandardOptions(): array
    {
        return $this->handle(
            SalesChannel::whereIn('type', [
                SalesChannelTypeEnum::WEBSITE,
                SalesChannelTypeEnum::PHONE,
                SalesChannelTypeEnum::SHOWROOM,
                SalesChannelTypeEnum::EMAIL,
                SalesChannelTypeEnum::OTHER,
                SalesChannelTypeEnum::MARKETPLACE,
            ])->get(['id', 'name', 'code'])
        );
    }

    public function getOptions(): array
    {
        return SalesChannel::whereIn('type', [
            SalesChannelTypeEnum::WEBSITE,
            SalesChannelTypeEnum::PHONE,
            SalesChannelTypeEnum::SHOWROOM,
            SalesChannelTypeEnum::EMAIL,
            SalesChannelTypeEnum::OTHER,
            SalesChannelTypeEnum::MARKETPLACE,
        ])
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'code', 'type'])
            ->map(fn ($channel) => [
                'id'   => $channel->id,
                'name' => $channel->name,
                'code' => $channel->code,
                'type' => $channel->type->value,
                'icon' => $channel->type->icon(),
            ])
            ->toArray();
    }


}
