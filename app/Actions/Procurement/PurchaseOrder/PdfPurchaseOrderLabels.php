<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelData;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockUnifiedLabelData;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\Response;

/**
 * The labels a supplier sticks on the goods of a purchase order, one label per carton, per SKO or per unit of every
 * line, each on its own label-sized page as the warehouse prints them one at a time. A line that cannot carry a label
 * (no SKO, no barcode for a unit label, a unified label with something missing) is left out and said why, so one
 * line never stops the rest. The file is reached through a signed link so the supplier can open it without an account.
 */
class PdfPurchaseOrderLabels
{
    use AsAction;

    public const array LEVELS = ['carton', 'sko', 'unit'];

    public const int LINK_DAYS = 7;

    /**
     * The file is rebuilt on every open of the link, so one link stops at what renders in a few seconds; a bigger
     * order is split by codes or levels.
     */
    public const int MAX_LABELS = 2000;

    /**
     * @param  list<string>  $levels
     * @param  list<string>  $codes
     * @return list<array{line: PurchaseOrderTransaction, code: string, level: string, labels: int, skipped: string|null}>
     */
    public function plan(PurchaseOrder $purchaseOrder, array $levels, array $codes = [], string $standard = 'localised', ?string $batch = null): array
    {
        $purchaseOrder->loadMissing(['purchaseOrderTransactions.supplierProduct', 'purchaseOrderTransactions.historicSupplierProduct', 'purchaseOrderTransactions.orgStock']);
        $codes       = array_map('strtolower', $codes);
        $isInProcess = $purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS;

        $plan = [];
        foreach (PdfPurchaseOrder::make()->lines($purchaseOrder) as $line) {
            $code = $line->orgStock?->code ?? $line->supplierProduct?->code ?? '';
            if ($codes && !array_intersect($codes, array_map('strtolower', array_filter([$line->orgStock?->code, $line->supplierProduct?->code])))) {
                continue;
            }

            foreach (array_intersect(self::LEVELS, $levels) as $level) {
                $plan[] = [
                    'line'    => $line,
                    'code'    => $code,
                    'level'   => $level,
                    'labels'  => $this->count($line, $level, $isInProcess),
                    'skipped' => $level === 'carton' && !$this->unitsPerCarton($line, $isInProcess)
                        ? __('This product has no units per carton, so its cartons cannot be counted')
                        : $this->skipReason($line, $level, $standard, $batch),
                ];
            }
        }

        return $plan;
    }

    /**
     * Cartons as the packing instructions count them, SKOs as packs of the supplier's (else the SKO's) pack size,
     * units as ordered.
     */
    private function count(PurchaseOrderTransaction $line, string $level, bool $isInProcess): int
    {
        $units           = (float) $line->quantity_ordered;
        $supplierProduct = $line->supplierProduct;

        return (int) ceil($units / match ($level) {
            'carton' => max(1, (int) $this->unitsPerCarton($line, $isInProcess)),
            'sko'    => max(1, (int) ($supplierProduct?->units_per_pack ?: $line->orgStock?->packedIn())),
            default  => 1,
        });
    }

    private function unitsPerCarton(PurchaseOrderTransaction $line, bool $isInProcess): ?int
    {
        return (($isInProcess ? null : $line->historicSupplierProduct?->units_per_carton) ?? $line->supplierProduct?->units_per_carton) ?: null;
    }

    private function skipReason(PurchaseOrderTransaction $line, string $level, string $standard, ?string $batch): ?string
    {
        if (!$line->orgStock) {
            return __('This product has no SKO yet');
        }

        if ($standard === 'unified') {
            $blockers = GetOrgStockUnifiedLabelData::run($line->orgStock, $level, $line->supplierProduct, $batch)['blockers'];

            return $blockers ? implode(' · ', $blockers) : null;
        }

        $label = GetOrgStockLabelData::run($line->orgStock, $level, $line->supplierProduct);

        return match ($level) {
            'unit'   => blank($label['barcode']['number']) ? __('This SKO has no barcode yet') : null,
            'carton' => blank($label['barcode']['number']) && blank($label['carton']['units_per_carton']) ? __('This product has no carton barcode nor units per carton') : null,
            default  => null,
        };
    }

    /**
     * @param  list<string>  $levels
     * @param  list<string>  $codes
     * @param  array<string, string>  $sizes
     *
     * @throws \Mpdf\MpdfException
     */
    public function handle(PurchaseOrder $purchaseOrder, array $levels, array $codes = [], array $sizes = [], string $standard = 'localised', ?string $batch = null): string
    {
        $printable = array_filter($this->plan($purchaseOrder, $levels, $codes, $standard, $batch), fn (array $item) => !$item['skipped'] && $item['labels'] > 0);
        abort_unless($printable, 404, __('None of these labels can be printed'));
        abort_if(array_sum(array_column($printable, 'labels')) > self::MAX_LABELS, 422, __('Too many labels for one file, ask for fewer codes or levels'));

        $merged = new Mpdf();
        $merged->SetTitle($this->filename($purchaseOrder));

        foreach ($printable as $item) {
            $label = PdfOrgStockLabel::make()->handle($item['line']->orgStock, $item['level'], array_filter([
                'supplier_product' => $item['line']->supplier_product_id,
                'size'             => $sizes[$item['level']] ?? null,
                'standard'         => $standard,
                'batch'            => $batch,
            ]))->getContent();

            $merged->setSourceFile(StreamReader::createByString($label));
            $template = $merged->importPage(1);
            $size     = $merged->getTemplateSize($template);

            for ($copy = 0; $copy < $item['labels']; $copy++) {
                $merged->AddPageByArray(['sheet-size' => [$size['width'], $size['height']], 'orientation' => 'P']);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('', Destination::STRING_RETURN);
    }

    public function filename(PurchaseOrder $purchaseOrder): string
    {
        return 'labels-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $purchaseOrder->reference).'.pdf';
    }

    /**
     * Signed on the path alone, so the proxies in front rewriting scheme or host cannot break the link, then given the
     * app domain the route is bound to.
     *
     * @param  list<string>  $levels
     * @param  list<string>  $codes
     * @param  array<string, string>  $sizes
     */
    public function url(PurchaseOrder $purchaseOrder, array $levels, array $codes = [], array $sizes = [], string $standard = 'localised', ?string $batch = null): string
    {
        $signed = URL::temporarySignedRoute('grp.purchase_order_labels', now()->addDays(self::LINK_DAYS), array_filter([
            'purchaseOrder' => $purchaseOrder->id,
            'levels'        => implode(',', $levels),
            'codes'         => implode(',', $codes),
            'sizes'         => $sizes ?: null,
            'standard'      => $standard === 'localised' ? null : $standard,
            'batch'         => $batch,
        ]), absolute: false);
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        return route('grp.purchase_order_labels', ['purchaseOrder' => $purchaseOrder->id, ...$query]);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $levels = array_values(array_intersect(self::LEVELS, explode(',', (string) $request->query('levels'))));
        abort_unless($levels, 404);

        $pdf = $this->handle(
            $purchaseOrder,
            $levels,
            array_values(array_filter(explode(',', (string) $request->query('codes')))),
            (array) $request->query('sizes', []),
            $request->query('standard') === 'unified' ? 'unified' : 'localised',
            $request->query('batch')
        );

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"')
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex');
    }
}
