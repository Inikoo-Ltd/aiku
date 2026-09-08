<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 10:20:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Models\Inventory\OrgStock;

trait WithOrgStockConsumables
{
    /**
     * Staff edit consumables as one "CODE x QUANTITY" per line, they are stored as
     * [{"code": "IAL01", "quantity": 1}].
     */
    protected function consumablesAsText(OrgStock $orgStock): string
    {
        return collect($orgStock->consumables ?? [])
            ->map(fn (array $consumable) => $consumable['code'].' x '.(0 + $consumable['quantity']))
            ->implode("\n");
    }

    /**
     * @return array<int, array{code: string, quantity: float}>|null
     */
    protected function parseConsumables(?string $text): ?array
    {
        $consumables = [];

        foreach (preg_split('/\R/', (string) $text) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (!preg_match('/^\s*(\S+)\s*x\s*([0-9]+(?:\.[0-9]+)?)\s*$/i', $line, $matches)) {
                return null;
            }

            $consumables[] = [
                'code'     => $matches[1],
                'quantity' => (float) $matches[2],
            ];
        }

        return $consumables;
    }
}
