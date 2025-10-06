<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:36:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\CRM;

use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateEmailSubscriptionsFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);


        $shops = Shop::where('organisation_id', $organisation->id)->where('is_aiku', true)->pluck('id')->toArray();


        // Iterate all customers in organisation_id = 3 using chunks to avoid memory issues
        Customer::query()
            ->whereIn('customers.shop_id', $shops)->whereNotNull('source_id')
            ->orderBy('id')
            ->chunkById(1000, function ($customers) {
                foreach ($customers as $customer) {
                    $sourceId = null;
                    if ($customer->source_id) {
                        $sourceId = $customer->source_id;
                    } else {
                        $sourceId = $customer->post_source_id;
                    }


                    if ($sourceId) {
                        $sourceData        = explode(':', $sourceId);
                        $auroraCustomerKey = $sourceData[1];


                        $auroraCustomerData = DB::connection('aurora')->table('Customer Dimension')->where('Customer Key', $auroraCustomerKey)->first();

                        if ($auroraCustomerData) {
                            $canNewsletter = $auroraCustomerData->{'Customer Send Newsletter'} == 'Yes';
                            $canMarketing  = $auroraCustomerData->{'Customer Send Email Marketing'} == 'Yes';
                            $canBasket     = $auroraCustomerData->{'Customer Send Basket Emails'} == 'Yes';

                            $dataToUpdate = [
                                'is_subscribed_to_newsletter'       => $canNewsletter,
                                'is_subscribed_to_marketing'        => $canMarketing,
                                'is_subscribed_to_abandoned_cart'   => $canMarketing,
                                'is_subscribed_to_reorder_reminder' => $canMarketing,
                                'is_subscribed_to_basket_low_stock' => $canMarketing,
                                'is_subscribed_to_basket_reminder'  => $canBasket
                            ];

                           // print $customer->slug."\n";
                           // print_r($dataToUpdate);

                            UpdateCustomerComms::run($customer->comms, $dataToUpdate, false);
                        }
                    }
                }
            }, 'id');
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:update_customer_subscriptions_from_aurora {organisation_id}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::find($command->argument('organisation_id'));

        try {
            $this->handle($organisation);

        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }


        return 0;
    }

}
