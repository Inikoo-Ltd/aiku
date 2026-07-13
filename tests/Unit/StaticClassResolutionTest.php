<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 11:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/*
 * Guards against static calls on classes that do not resolve (typically a missing
 * `use` import, so `Foo::run()` resolves inside the current namespace and fatals
 * only when that code path executes in production).
 *
 * KNOWN_UNRESOLVED is the pre-existing debt baseline: fix entries and remove them,
 * never add to it.
 */

const KNOWN_UNRESOLVED = [
    'App\Actions\Billables\Service\UI\ServicestateEnum',
    'App\Actions\Billables\ShippingZone\Hydrators\ShippingZone',
    'App\Actions\CRM\WebUser\WebUserFailedLogIn',
    'App\Actions\Catalogue\Product\WebPageStateEnum',
    'App\Actions\Fulfilment\PalletDelivery\UI\ServicestateEnum',
    'App\Actions\Fulfilment\PalletReturn\UI\ServicestateEnum',
    'App\Actions\Fulfilment\UI\Catalogue\Services\ServicestateEnum',
    'App\Actions\Google\Analytics\GetAnalytics',
    'App\Actions\HumanResources\Employee\GenerateEmployeeLeaveBalance',
    'App\Actions\Inventory\OrgStock\UI\tabsEnum',
    'App\Actions\Ordering\Order\Traits\Sentry',
    'App\Actions\Procurement\OrgSupplier\UI\ShowSupplier',
    'App\Actions\Retina\Pricing\UI\ServicestateEnum',
    'App\Actions\SysAdmin\Organisation\UI\GetTimezonesOptions',
    'App\Actions\Transfers\Aurora\Db',
    'App\Actions\Web\Banner\UI\CustomerHistoryResource',
    'App\Actions\Web\Banner\UI\IndexCustomerHistory',
    'App\Enums\Portfolio\Banner\BannerStateEnum',
    'App\Http\Resources\Portfolio\BannerResource',
    'App\Http\Resources\Web\AnnouncementStatusenum',
    'App\Models\HumanResources\Overtime\OvertimeRequestApprover',
    'App\Models\Portfolio\Banner',
    'App\Models\Portfolio\PortfolioWebsite',
    'App\Models\Traits\GetPortfolioWebsitesOptions',
    'App\Models\Traits\PortfolioWebsite',
    'App\Transfers\Aurora\Db',
    'Arr',
    'Artisan',
    'Cache',
    'DB',
    'Excel',
    'Hash',
    'Http',
    'Log',
    'Ollama',
    'Route',
    'Sentry',
    'Str',
    'WithProformaInvoicePdf',
    'WithTimeSeriesRedo',
];

function findUnresolvedStaticCalls(string $dir): array
{
    $unresolved = [];
    $files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $tokens    = token_get_all(file_get_contents($file->getPathname()));
        $namespace = '';
        $uses      = [];
        $count     = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_NAME_QUALIFIED, T_STRING])) {
                        $namespace = $tokens[$j][1];
                        break;
                    }
                    if ($tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($token[0] === T_USE) {
                $imported = '';
                $alias    = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];
                    if ($next === ';' || $next === '{') {
                        break;
                    }
                    if (is_array($next)) {
                        if (in_array($next[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) && $imported === '') {
                            $imported = ltrim($next[1], '\\');
                        } elseif ($next[0] === T_AS) {
                            for ($k = $j + 1; $k < $count; $k++) {
                                if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                                    $alias = $tokens[$k][1];
                                    break;
                                }
                            }
                        }
                    }
                }
                if ($imported && !str_contains($imported, 'function')) {
                    $shortName        = str_contains($imported, '\\') ? substr($imported, strrpos($imported, '\\') + 1) : $imported;
                    $uses[$alias ?? $shortName] = $imported;
                }
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_DOUBLE_COLON) {
                    continue;
                }

                $name = $token[1];
                if (in_array(strtolower($name), ['self', 'static', 'parent', 'true', 'false', 'null'])) {
                    continue;
                }

                if ($token[0] === T_NAME_FULLY_QUALIFIED) {
                    $fqcn = ltrim($name, '\\');
                } elseif ($token[0] === T_NAME_QUALIFIED) {
                    $first = substr($name, 0, strpos($name, '\\'));
                    $fqcn  = isset($uses[$first])
                        ? $uses[$first].substr($name, strlen($first))
                        : ($namespace ? $namespace.'\\'.$name : $name);
                } else {
                    $fqcn = $uses[$name] ?? ($namespace ? $namespace.'\\'.$name : $name);
                }

                if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn) && !enum_exists($fqcn)) {
                    $unresolved[$fqcn][] = str_replace(base_path().'/', '', $file->getPathname()).':'.$token[2];
                }
            }
        }
    }

    return $unresolved;
}

test('all statically called classes resolve', function () {
    $unresolved = findUnresolvedStaticCalls(app_path());

    $new = array_diff_key($unresolved, array_flip(KNOWN_UNRESOLVED));

    $message = '';
    foreach ($new as $fqcn => $locations) {
        $message .= "\n$fqcn (probably a missing `use` import) at:\n  ".implode("\n  ", array_slice($locations, 0, 5));
    }

    expect($new)->toBeEmpty($message);
});
