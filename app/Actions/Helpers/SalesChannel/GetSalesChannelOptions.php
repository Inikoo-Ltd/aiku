<?php

namespace App\Actions\Helpers\SalesChannel;

use App\Models\Ordering\SalesChannel;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Models\Catalogue\Shop;

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

    public function getStandardOptions(?Shop $shop = null): array
    {
        if ($shop) {
            $salesChannels = $shop->salesChannels()->get();
        } else {
            $salesChannels = SalesChannel::where('is_seeded', true)->get();
        }

        return $this->handle($salesChannels);
    }

    /**
     * Get detailed options specifically formatted for the UI (Vue component).
     *
     * @param Shop|null $shop
     * @return array
     */
    public function getOptions(?Shop $shop = null): array
    {
        if ($shop) {
            $query = $shop->salesChannels();
        } else {
            $query = SalesChannel::where('is_seeded', true);
        }

        return $query
            ->orderBy('sales_channels.id', 'asc')
            ->get([
                'sales_channels.id',
                'sales_channels.name',
                'sales_channels.code',
                'sales_channels.type',
            ])
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
