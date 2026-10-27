<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Unit;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotesState;
use App\Actions\Traits\AsUniqueUntilProcessingAction;
use App\Jobs\BoundedUniqueUntilProcessingJobDecorator;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

function actionsUniqueUntilProcessing(): array
{
    $classes = [];
    foreach (Finder::create()->files()->in(app_path('Actions'))->name('*.php')->contains('/^(final |abstract )?class\s+\w+[^{]*implements[^{]*\bShouldBeUniqueUntilProcessing\b/m') as $file) {
        $classes[] = 'App\\Actions\\'.str_replace('/', '\\', Str::beforeLast($file->getRelativePathname(), '.php'));
    }

    return $classes;
}

test('every action unique until processing is queued in a decorator the queue releases when the run starts', function () {
    $classes = actionsUniqueUntilProcessing();

    expect($classes)->not->toBeEmpty()
        ->and(class_implements(BoundedUniqueUntilProcessingJobDecorator::class))->toHaveKey(ShouldBeUniqueUntilProcessing::class);

    foreach ($classes as $class) {
        expect(class_implements($class))->toHaveKey(ShouldBeUniqueUntilProcessing::class)
            ->and(in_array(AsUniqueUntilProcessingAction::class, class_uses_recursive($class), true))->toBeTrue("$class must use AsUniqueUntilProcessingAction");
    }
});

test('delivery note state counters take a state change that arrives while they recount', function () {
    $classes = actionsUniqueUntilProcessing();

    foreach ([
        GroupHydrateDeliveryNotesState::class,
        OrganisationHydrateDeliveryNotesState::class,
        OrganisationHydrateShopTypeDeliveryNotesState::class,
        ShopHydrateDeliveryNotesState::class,
    ] as $class) {
        expect($classes)->toContain($class)
            ->and($class::makeUniqueJob(1))->toBeInstanceOf(BoundedUniqueUntilProcessingJobDecorator::class);
    }
});
