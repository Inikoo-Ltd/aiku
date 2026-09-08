<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 15:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Accounting\Payment\UI\IndexPaymentMethods;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('How customers paid, for an organisation or one of its shops over a date range: one row per provider (checkout.com, Braintree, bank, PayPal...) + payment method (card, Apple Pay, Klarna, PayPal...) + card scheme, with attempts, failed attempts, successful amount in the accounting currency and last use. Use this for questions about payment methods, card vs wallet vs Klarna share, declined payments or payment success rates. Amounts are successful payments only, refunds are not counted.')]
#[IsReadOnly]
class PaymentMethodsTool extends AikuOrganisationTool
{
    protected function permission(): OrganisationPermissionsEnum
    {
        return OrganisationPermissionsEnum::ACCOUNTING_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'shop'         => ['sometimes', 'string'],
            'from'         => ['sometimes', 'date'],
            'to'           => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $organisation = $this->authorisedOrganisation($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        $parent = $organisation;
        if ($request->has('shop')) {
            $identifier = strtolower((string) $request->string('shop'));
            $parent     = $organisation->shops()
                ->where(fn ($q) => $q->whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier]))
                ->first();
            if (!$parent) {
                return Response::error('Shop not found in '.$organisation->name.'. Shops: '.$organisation->shops()->pluck('slug')->implode(', '));
            }
        }

        $from = $request->has('from') ? Carbon::parse($request->string('from'))->startOfDay() : null;
        $to   = $request->has('to') ? Carbon::parse($request->string('to'))->endOfDay() : null;

        $report = IndexPaymentMethods::report($parent, $from, $to);

        return Response::json([
            'organisation' => $organisation->name,
            'shop'         => $parent instanceof Shop ? $parent->name : null,
            'from'         => $from?->toDateString(),
            'to'           => $to?->toDateString(),
            'currency'     => $report['currency_code'],
            'rows'         => collect($report['rows'])->map(fn ($row) => [
                'provider'        => $row['payment_account_label'],
                'method'          => $row['method_label'],
                'card_scheme'     => $row['sub_method_label'],
                'attempts'        => $row['number_payments'],
                'failed'          => $row['number_payments'] - $row['number_success'],
                'amount'          => round($row['total_sales'], 2),
                'last_payment_at' => $row['last_payment_at'],
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation slug, code or name, e.g. aw')->required(),
            'shop'         => $schema->string()->description('Optional shop slug or code inside the organisation, e.g. awd; omit for the whole organisation'),
            'from'         => $schema->string()->description('Start date (Y-m-d); omit for all time'),
            'to'           => $schema->string()->description('End date (Y-m-d), inclusive; omit for all time'),
        ];
    }
}
