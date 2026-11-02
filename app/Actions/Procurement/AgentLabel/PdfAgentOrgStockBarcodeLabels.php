<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-02 10:17
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentLabel;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelData;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockUnifiedLabelData;
use App\Actions\Inventory\OrgStock\UI\PdfOrgStockLabel;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\ActionRequest;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The barcode labels of many SKOs an agent buys for us in one file, one label per SKO on its own label-sized page, as
 * the agent ticks them on the barcodes page. A SKO whose label cannot be printed at that level is left out, so one
 * missing barcode never stops the rest; plan() says which and why before the file is asked for.
 */
class PdfAgentOrgStockBarcodeLabels extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public const array LEVELS = ['unit', 'sko', 'carton'];

    /**
     * The file is rebuilt on every open, so one print stops at what renders in a few seconds.
     */
    public const int MAX_ORG_STOCKS = 200;

    public function rules(): array
    {
        return [
            'org_stocks' => ['required', 'string'],
            'level'      => ['required', 'string', 'in:'.implode(',', self::LEVELS)],
            'standard'   => ['sometimes', 'string', 'in:unified,localised'],
            'batch'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'size'       => ['sometimes', 'nullable', 'string', 'in:'.implode(',', array_keys(PdfOrgStockLabel::SIZES))],
        ];
    }

    /**
     * @param  list<int>  $orgStockIds
     * @return Collection<int, OrgStock>
     */
    public function orgStocks(Agent $agent, array $orgStockIds): Collection
    {
        abort_if(count($orgStockIds) > self::MAX_ORG_STOCKS, 422, __('Up to :max products can be printed at once', ['max' => self::MAX_ORG_STOCKS]));

        return GetAgentOrgStocks::run($agent)
            ->whereIn('org_stocks.id', $orgStockIds)
            ->with(['stock', 'tradeUnits'])
            ->orderBy('org_stocks.code')
            ->get();
    }

    /**
     * @param  Collection<int, OrgStock>  $orgStocks
     * @return list<array{id: int, code: string, reason: string}>
     */
    public function plan(Collection $orgStocks, string $level, string $standard, ?string $batch): array
    {
        $skipped = [];
        foreach ($orgStocks as $orgStock) {
            $reason = $this->skipReason($orgStock, $level, $standard, $batch);
            if ($reason) {
                $skipped[] = ['id' => $orgStock->id, 'code' => $orgStock->code, 'reason' => $reason];
            }
        }

        return $skipped;
    }

    private function skipReason(OrgStock $orgStock, string $level, string $standard, ?string $batch): ?string
    {
        if ($standard === 'unified') {
            $blockers = GetOrgStockUnifiedLabelData::run($orgStock, $level, null, $batch)['blockers'];

            return $blockers ? implode(' · ', $blockers) : null;
        }

        $label = GetOrgStockLabelData::run($orgStock, $level);

        return match ($level) {
            'unit'   => blank($label['barcode']['number']) ? __('This SKO has no barcode yet') : null,
            'carton' => blank($label['barcode']['number']) && blank($label['carton']['units_per_carton']) ? __('This product has no carton barcode nor units per carton') : null,
            default  => null,
        };
    }

    /**
     * A label that still fails while it renders (its texts do not fit, say) is left out like the planned ones.
     *
     * @param  Collection<int, OrgStock>  $orgStocks
     * @param  array<string, mixed>  $options
     *
     * @throws \Mpdf\MpdfException
     */
    public function handle(Collection $orgStocks, string $level, array $options): string
    {
        $skippedIds = array_column($this->plan($orgStocks, $level, $options['standard'] ?? 'localised', $options['batch'] ?? null), 'id');

        $merged = new Mpdf();
        $merged->SetTitle($this->filename($level));
        $pages = 0;

        foreach ($orgStocks->reject(fn (OrgStock $orgStock) => in_array($orgStock->id, $skippedIds, true)) as $orgStock) {
            try {
                $label = PdfOrgStockLabel::make()->handle($orgStock, $level, $options)->getContent();
            } catch (HttpException) {
                continue;
            }

            $pageCount = $merged->setSourceFile(StreamReader::createByString($label));
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $merged->importPage($page);
                $size     = $merged->getTemplateSize($template);
                $merged->AddPageByArray(['sheet-size' => [$size['width'], $size['height']], 'orientation' => 'P']);
                $merged->useTemplate($template);
                $pages++;
            }
        }

        abort_unless($pages, 404, __('None of these labels can be printed'));

        return $merged->Output('', Destination::STRING_RETURN);
    }

    public function filename(string $level): string
    {
        return 'barcode-labels-'.$level.'.pdf';
    }

    /**
     * @return list<int>
     */
    public function orgStockIds(ActionRequest $request): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $request->input('org_stocks'))))));
    }

    /**
     * @return array<string, mixed>
     */
    public function labelOptions(ActionRequest $request): array
    {
        return array_filter([
            'standard' => $request->input('standard') === 'unified' ? 'unified' : 'localised',
            'batch'    => $request->input('batch') ? strtoupper(trim((string) $request->input('batch'))) : null,
            'size'     => $request->input('size'),
        ]);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        $level = $request->input('level');
        $pdf   = $this->handle($this->orgStocks($agent, $this->orgStockIds($request)), $level, $this->labelOptions($request));

        return response($pdf, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($level).'"');
    }
}
