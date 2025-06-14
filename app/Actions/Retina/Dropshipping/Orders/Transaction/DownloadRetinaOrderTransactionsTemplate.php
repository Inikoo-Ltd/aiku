<?php

/*
 * author Arya Permana - Kirin
 * created on 12-06-2025-15h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Ordering\Order\DownloadOrderTransactionsTemplate;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadRetinaOrderTransactionsTemplate
{
    use AsAction;
    use WithAttributes;

    public function handle(): BinaryFileResponse
    {
        return DownloadOrderTransactionsTemplate::run();
    }

    public function asController(Order $order): BinaryFileResponse
    {
        return $this->handle();
    }
}
