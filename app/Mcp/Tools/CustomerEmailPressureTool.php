<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Email pressure for a shop\'s customers: how many marketing emails each received over a date range.')]
class CustomerEmailPressureTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::CRM_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'from'  => ['required', 'date'],
            'to'    => ['required', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $from = $request->date('from');
        $to = $request->date('to')->endOfDay();
        $limit = $request->integer('limit', 10);

        $emailCounts = DB::table('dispatched_emails')
            ->select(
                'mailshot_recipients.recipient_id',
                DB::raw('count(*) as emails_sent')
            )
            ->join('outboxes', 'dispatched_emails.outbox_id', '=', 'outboxes.id')
            ->join('mailshot_recipients', 'mailshot_recipients.dispatched_email_id', '=', 'dispatched_emails.id')
            ->where('outboxes.shop_id', $shop->id)
            ->where('mailshot_recipients.recipient_type', 'Customer')
            ->whereBetween('dispatched_emails.sent_at', [$from, $to])
            ->groupBy('mailshot_recipients.recipient_id')
            ->orderByDesc('emails_sent')
            ->limit($limit)
            ->get();

        $customerIds = $emailCounts->pluck('recipient_id')->toArray();
        $customers = Customer::whereIn('id', $customerIds)->get(['id', 'name'])->keyBy('id');

        $topRecipients = $emailCounts->map(function ($record) use ($customers) {
            $customer = $customers->get($record->recipient_id);
            return [
                'customer' => $customer?->name ?? 'Unknown',
                'emails_sent' => (int) $record->emails_sent,
            ];
        })->values();

        $totalEmails = DB::table('dispatched_emails')
            ->join('outboxes', 'dispatched_emails.outbox_id', '=', 'outboxes.id')
            ->join('mailshot_recipients', 'mailshot_recipients.dispatched_email_id', '=', 'dispatched_emails.id')
            ->where('outboxes.shop_id', $shop->id)
            ->where('mailshot_recipients.recipient_type', 'Customer')
            ->whereBetween('dispatched_emails.sent_at', [$from, $to])
            ->count();

        $distinctCustomers = DB::table('dispatched_emails')
            ->select(DB::raw('count(distinct mailshot_recipients.recipient_id) as count'))
            ->join('outboxes', 'dispatched_emails.outbox_id', '=', 'outboxes.id')
            ->join('mailshot_recipients', 'mailshot_recipients.dispatched_email_id', '=', 'dispatched_emails.id')
            ->where('outboxes.shop_id', $shop->id)
            ->where('mailshot_recipients.recipient_type', 'Customer')
            ->whereBetween('dispatched_emails.sent_at', [$from, $to])
            ->value('count');

        $avgPerCustomer = $distinctCustomers > 0 ? round($totalEmails / $distinctCustomers, 1) : 0;

        return Response::json([
            'shop'                   => $shop->name,
            'from'                   => $request->string('from'),
            'to'                     => $request->string('to'),
            'total_emails'           => $totalEmails,
            'customers_reached'      => (int) $distinctCustomers,
            'average_per_customer'   => $avgPerCustomer,
            'top_recipients'         => $topRecipients,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug')->required(),
            'from'  => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'    => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'limit' => $schema->integer()->description('Maximum customers to return, default 10, max 50')->default(10),
        ];
    }
}
