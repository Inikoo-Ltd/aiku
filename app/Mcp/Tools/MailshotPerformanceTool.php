<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Performance metrics of a shop\'s mailshots: sent, delivered, opened, clicked, unsubscribed with engagement rates.')]
class MailshotPerformanceTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::MARKETING_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop'  => ['required', 'string'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $query = Mailshot::where('shop_id', $shop->id)
            ->with('stats')
            ->latest('sent_at');

        if ($request->has('from') && $request->string('from')) {
            $query->where('sent_at', '>=', $request->date('from'));
        }

        if ($request->has('to') && $request->string('to')) {
            $query->where('sent_at', '<=', $request->date('to')->endOfDay());
        }

        $limit = $request->integer('limit', 10);
        $mailshots = $query->limit($limit)->get();

        $data = $mailshots->map(function (Mailshot $mailshot) {
            $stats = $mailshot->stats;
            $delivered = $stats?->number_dispatched_emails_state_delivered ?? 0;
            $opened = $stats?->number_dispatched_emails_state_opened ?? 0;
            $clicked = $stats?->number_dispatched_emails_state_clicked ?? 0;

            $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 1) : 0;
            $clickRate = $delivered > 0 ? round(($clicked / $delivered) * 100, 1) : 0;

            return [
                'subject'       => $mailshot->subject,
                'state'         => $mailshot->state->value,
                'date'          => $mailshot->sent_at?->format('Y-m-d'),
                'sent'          => $stats?->number_dispatched_emails ?? 0,
                'delivered'     => $delivered,
                'opened'        => $opened,
                'clicked'       => $clicked,
                'unsubscribed'  => $stats?->number_dispatched_emails_state_unsubscribed ?? 0,
                'open_rate'     => $openRate,
                'click_rate'    => $clickRate,
            ];
        })->values();

        return Response::json([
            'shop'      => $shop->name,
            'mailshots' => $data,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop'  => $schema->string()->description('Shop slug')->required(),
            'from'  => $schema->string()->description('Start date (Y-m-d), optional')->nullable(),
            'to'    => $schema->string()->description('End date (Y-m-d), optional')->nullable(),
            'limit' => $schema->integer()->description('Maximum mailshots to return, default 10, max 50')->default(10),
        ];
    }
}
