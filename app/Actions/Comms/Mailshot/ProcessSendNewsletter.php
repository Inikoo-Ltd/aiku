<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 11:56:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Email;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\Comms\Traits\WithSendCustomerOutboxEmail;
use App\Actions\Comms\Email\SendNewsletterToCustomerEmail;
use App\Services\QueryBuilder;

class ProcessSendNewsletter
{
    use AsAction;
    use WithSendCustomerOutboxEmail;

    public string $jobQueue = 'default-long';

    public function tags(): array
    {
        return ['send_newsletter'];
    }

    public function handle(Mailshot $mailshot): void
    {


        // step by step create to send newsletter
        // get the customers
        // check if the customer can be contacted by email
        // if can be contacted by email, send the email
        // update the mailshot send channel
        // update the mailshot

        //  user customer as base query
        $queryRecipientBuilder = QueryBuilder::for(Customer::class)
            ->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
            ->where('shop_id', $mailshot->shop_id)
            ->where('customer_comms.is_subscribed_to_newsletter', true)
            ->where('customers.email', '!=', null)
            ->select('customers.id', 'customers.shop_id', 'customers.name', 'customers.email');

        //  send every 250 recipients
        $queryRecipientBuilder->chunk(250, function ($chunk) use ($mailshot) {
            foreach ($chunk as $model) {
                if (filter_var($model->email, FILTER_VALIDATE_EMAIL)) {
                    SendNewsletterToCustomerEmail::dispatch($model, [], $mailshot);
                }
            }
        });
    }

    public string $commandSignature = 'mailshot:send {mailshot}';


    public function asCommand(Command $command): int
    {
        try {
            $mailshot = Mailshot::where('slug', $command->argument('mailshot'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }


        $this->handle($mailshot);

        return 0;
    }
}
