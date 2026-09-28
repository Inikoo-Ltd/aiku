<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\PurchaseOrder;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Str;

enum PurchaseOrderAttachmentScopeEnum: string
{
    use EnumHelperTrait;

    case PROFORMA     = 'Proforma';
    case INVOICE      = 'Invoice';
    case PACKING_LIST = 'Packing list';
    case OTHER        = 'Other';

    public static function labels(): array
    {
        return [
            'Proforma'     => __('Proforma'),
            'Invoice'      => __('Invoice'),
            'Packing list' => __('Packing list'),
            'Other'        => __('Other'),
        ];
    }

    /**
     * @return array<int, array{name: string, code: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $code) => ['name' => $label, 'code' => $code])
            ->values()
            ->all();
    }

    /**
     * Proforma is checked before invoice because suppliers name them "proforma invoice".
     */
    public static function guessFromFileName(string $fileName): self
    {
        $name = Str::lower($fileName);

        return match (true) {
            Str::contains($name, ['proforma', 'pro-forma', 'pro forma']) => self::PROFORMA,
            Str::contains($name, ['packing', 'packlist']) => self::PACKING_LIST,
            Str::contains($name, ['invoice', 'factura', 'faktura', 'rechnung', 'fattura']) => self::INVOICE,
            default => self::OTHER,
        };
    }
}
