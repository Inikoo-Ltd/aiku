<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Helpers\Media\StoreMediaFromIcon;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\SupplyChain\Supplier;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A supplier without a picture gets a generated one (an identicon seeded by its slug, so it never changes), the
 * way users get theirs. A picture staff set is never replaced.
 */
class SetIconAsSupplierImage
{
    use AsAction;
    use WithAttachMediaToModel;

    public string $commandSignature = 'supplier:set-icons';

    public string $commandDescription = 'Give every supplier without a picture a generated one';

    public function handle(Supplier $supplier): Supplier
    {
        if ($supplier->image_id) {
            return $supplier;
        }

        $media = StoreMediaFromIcon::run($supplier);
        if ($media) {
            $this->attachMediaToModel($supplier, $media, 'avatar');
            $supplier->updateQuietly(['image_id' => $media->id]);
        }

        return $supplier;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $count = 0;
        Supplier::whereNull('image_id')->orderBy('id')->each(function (Supplier $supplier) use (&$count) {
            if ($this->handle($supplier)->image_id) {
                $count++;
            }
        });
        $command->info("$count suppliers got a picture");

        return 0;
    }
}
