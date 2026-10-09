<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\Helpers\Upload;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The signed declaration a supplier sends with an upload batch (Supplier declarations tab of the v7 template).
 *
 * @property int $id
 * @property int $group_id
 * @property int $supplier_id
 * @property int|null $upload_id
 * @property string|null $company
 * @property string|null $signed_by
 * @property string|null $position
 * @property Carbon|null $signed_on
 * @property list<array{question: string, answer: string}> $answers
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read Upload|null $upload
 * @mixin \Eloquent
 */
class SupplierDeclaration extends Model
{
    use InGroup;

    protected $casts = [
        'answers'   => 'array',
        'data'      => 'array',
        'signed_on' => 'date',
    ];

    protected $attributes = [
        'answers' => '[]',
        'data'    => '{}',
    ];

    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
