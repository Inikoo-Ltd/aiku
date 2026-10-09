<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierDeclaration;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The supplier's signed v7 declarations, newest first, with the answers that are not a plain yes.
 */
class GetSupplierDeclarations
{
    use AsObject;

    public function handle(Supplier $supplier): array
    {
        return $supplier->declarations()->with('upload')->latest('id')->get()->map(fn (SupplierDeclaration $declaration) => [
            'id'        => $declaration->id,
            'company'   => $declaration->company,
            'signed_by' => $declaration->signed_by,
            'position'  => $declaration->position,
            'signed_on' => $declaration->signed_on?->toDateString(),
            'file'      => $declaration->upload?->original_filename,
            'received'  => $declaration->created_at?->toDateString(),
            'answers'   => collect($declaration->answers)->map(fn (array $answer) => [
                'question' => (string)($answer['question'] ?? ''),
                'answer'   => (string)($answer['answer'] ?? ''),
                'is_yes'   => str_starts_with(mb_strtolower((string)($answer['answer'] ?? '')), 'yes'),
            ])->all(),
        ])->all();
    }
}
