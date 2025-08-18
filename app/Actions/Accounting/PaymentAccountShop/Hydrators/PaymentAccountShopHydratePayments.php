<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-16h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccountShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use Checkout\Payments\PaymentType;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;


class PaymentAccountShopHydratePayments implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(PaymentAccountShop $paymentAccountShop): string
    {
        return $paymentAccountShop->id;
    }


    public function handle(PaymentAccountShop $paymentAccountShop): void
    {
        $paymentsQuery = DB::table('payments')
           ->where('payments.payment_account_shop_id', $paymentAccountShop->id);


        $paymentsCount = (clone $paymentsQuery)->count();
        $paymentsAmount = (clone $paymentsQuery)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->sum('amount');
        $orgPaymentsAmount = (clone $paymentsQuery)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->sum('org_amount');
        $grpPaymentsAmount = (clone $paymentsQuery)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->sum('grp_amount');
        $refundsAmount = (clone $paymentsQuery)
            ->where('type', PaymentTypeEnum::REFUND)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->sum('amount');
        $orgRefundsAmount = (clone $paymentsQuery)
            ->where('type', PaymentTypeEnum::REFUND)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->sum('org_amount');
        $grpRefundsAmount = (clone $paymentsQuery)
            ->where('type', PaymentTypeEnum::REFUND)
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->sum('grp_amount');

        $stats = [
            'number_payments' => $paymentsCount,
            'amount_successfully_paid' => $this->formatAmount($paymentsAmount),
            'org_amount_successfully_paid' => $this->formatAmount($orgPaymentsAmount),
            'grp_amount_successfully_paid' => $this->formatAmount($grpPaymentsAmount),
            'amount_refunded' => $this->formatAmount($refundsAmount),
            'org_amount_refunded' => $this->formatAmount($orgRefundsAmount),
            'grp_amount_refunded' => $this->formatAmount($grpRefundsAmount),
        ];

        foreach (PaymentTypeEnum::cases() as $type) {
            foreach (PaymentStateEnum::cases() as $state) {
                $stats["number_payments_type_{$type->value}_state_{$state->value}"] = (clone $paymentsQuery)
                    ->where('type', $type)
                    ->where('state', $state)
                    ->count();
            }
        }

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'state',
                enum: PaymentStateEnum::class,
                models: Payment::class,
                where: function ($q) use ($paymentAccountShop) {
                    $q->where('payment_account_shop_id', $paymentAccountShop->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'payments',
                field: 'type',
                enum: PaymentTypeEnum::class,
                models: Payment::class,
                where: function ($q) use ($paymentAccountShop) {
                    $q->where('payment_account_shop_id', $paymentAccountShop->id);
                }
            )
        );

        $paymentAccountShop->stats()->update($stats);
    }

    private function formatAmount($amount): float
    {
        return round($amount ?? 0, 2);
    }


    public string $commandSignature = 'hydrate:payment-account-shop {shop?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('shop')) {
            $shop = Shop::where('slug', $command->argument('shop'))->first();
        
            $count = PaymentAccountShop::where('shop_id', $shop->id)->count();
            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            PaymentAccountShop::where('shop_id', $shop->id)->orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        } else {
            $count = PaymentAccountShop::count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            PaymentAccountShop::orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar) {
                    foreach ($models as $model) {
                        $this->handle($model);
                        $bar->advance();
                    }
                });
        }
    }






}
