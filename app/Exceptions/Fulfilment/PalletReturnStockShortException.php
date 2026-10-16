<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 16 Oct 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exceptions\Fulfilment;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PalletReturnStockShortException extends ValidationException
{
    public const string REASON_SHORT = 'short';
    public const string REASON_OUT_OF_STOCK = 'out_of_stock';
    public const string REASON_DISCONTINUED = 'discontinued';

    /**
     * @var array<int, array{stored_item_id: int, portfolio_id: int|null, reference: string, quantity_ordered: float, quantity_available: float, reason: string}>
     */
    public array $lines = [];

    /**
     * @param array<int, array{stored_item_id: int, portfolio_id: int|null, reference: string, quantity_ordered: float, quantity_available: float, reason: string}> $lines
     */
    public static function forLines(array $lines): self
    {
        $summary = trans_choice(':count line is short of stock.|:count lines are short of stock.', count($lines), ['count' => count($lines)]);

        $messages = ['message' => [$summary]];
        foreach ($lines as $line) {
            $messages['lines.'.$line['reference']] = [self::lineMessage($line)];
        }

        $exception          = new self(ValidationException::withMessages($messages)->validator);
        $exception->lines   = $lines;
        $exception->message = $summary;

        return $exception;
    }

    /**
     * @param array{reference: string, quantity_ordered: float, quantity_available: float, reason: string} $line
     */
    private static function lineMessage(array $line): string
    {
        return match ($line['reason']) {
            self::REASON_DISCONTINUED => __(':reference is discontinued and cannot be returned.', ['reference' => $line['reference']]),
            self::REASON_OUT_OF_STOCK => __(':reference is out of stock, :ordered ordered.', ['reference' => $line['reference'], 'ordered' => $line['quantity_ordered']]),
            default => __(':reference: :ordered ordered, only :available available.', [
                'reference' => $line['reference'],
                'ordered'   => $line['quantity_ordered'],
                'available' => $line['quantity_available'],
            ]),
        };
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->inertia() || !$request->expectsJson()) {
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => $this->errors(),
            'lines'   => $this->lines,
        ], $this->status);
    }
}
