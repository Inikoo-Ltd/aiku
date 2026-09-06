<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 10:12:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\TiktokUser;
use Lorisleiva\Actions\Concerns\AsAction;

class ReconnectTiktokUser
{
    use AsAction;

    /**
     * A seller who closed the channel and authorises the same TikTok account again gets the
     * channel back with its portfolios, instead of a second channel next to the closed one.
     */
    public function handle(TiktokUser $tiktokUser): TiktokUser
    {
        $tiktokUser->restore();

        $customerSalesChannel = $tiktokUser->customerSalesChannel;

        if ($customerSalesChannel) {
            $wasClosed = $customerSalesChannel->status == CustomerSalesChannelStatusEnum::CLOSED;

            UpdateCustomerSalesChannel::run($customerSalesChannel, [
                'platform_user_type' => class_basename($tiktokUser),
                'platform_user_id'   => $tiktokUser->id,
                'status'             => CustomerSalesChannelStatusEnum::OPEN,
                'state'              => CustomerSalesChannelStateEnum::AUTHENTICATED,
                'name'               => preg_replace('/( - deleted - \d+)+$/', '', (string) $customerSalesChannel->name) ?: null,
                'closed_at'          => null,
            ]);

            if ($wasClosed) {
                foreach ($customerSalesChannel->portfolios as $portfolio) {
                    UpdatePortfolio::run($portfolio, ['status' => true]);
                }
            }
        }

        return $tiktokUser->refresh();
    }
}
