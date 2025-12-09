<?php

namespace App\Actions\Dropshipping\Aiku;

use App\Events\CloneRetinaPortfolioProgressEvent;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\CRM\WebUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClonePortfolioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $sourceCustomerSalesChannel;
    public $user;

    public function __construct(CustomerSalesChannel $sourceCustomerSalesChannel, WebUser $user)
    {
        $this->sourceCustomerSalesChannel = $sourceCustomerSalesChannel;
        $this->user = $user;
    }

    public function handle()
    {
        $portfolios = $this->sourceCustomerSalesChannel->portfolios()->get();
        $total = $portfolios->count();

        $processed = 0;
        $success = 0;
        $fails = 0;

        $newCustomerSalesChannel = $this->sourceCustomerSalesChannel->replicate();
        $newCustomerSalesChannel->name = $this->sourceCustomerSalesChannel->name . " (Clone)";
        $newCustomerSalesChannel->save();

        foreach ($portfolios as $portfolio) {
            try {
                $newPortfolio = $portfolio->replicate();
                $newPortfolio->customer_sales_channel_id = $newCustomerSalesChannel->id;
                $newPortfolio->save();

                $success++;
            } catch (\Exception $e) {
                $fails++;
            }

            $processed++;

            event(new CloneRetinaPortfolioProgressEvent(
                userId: $this->user->id,
                actionId: $newCustomerSalesChannel->id,
                actionType: 'clone_portfolio',
                total: $total,
                done: $processed,
                numberSuccess: $success,
                numberFails: $fails
            ));
        }
    }
}
