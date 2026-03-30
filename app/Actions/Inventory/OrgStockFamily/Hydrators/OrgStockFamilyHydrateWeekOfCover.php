<?php

namespace App\Actions\Inventory\OrgStockFamily\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStockFamily;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockFamilyHydrateWeekOfCover implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-family-week-of-cover {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStockFamily::class;
    }

    public function getJobUniqueId(OrgStockFamily $orgStockFamily): string
    {
        return $orgStockFamily->id;
    }

    public function handle(OrgStockFamily $orgStockFamily): void
    {
        $totalQuantityAvailable = $orgStockFamily->orgStocks()->sum('quantity_available');

        $result = DB::table('invoice_transactions')
            ->join('invoice_transaction_has_org_stocks', 'invoice_transaction_has_org_stocks.invoice_transaction_id', '=', 'invoice_transactions.id')
            ->where('invoice_transaction_has_org_stocks.org_stock_family_id', $orgStockFamily->id)
            ->whereNull('invoice_transactions.deleted_at')
            ->selectRaw('COALESCE(SUM(invoice_transactions.quantity), 0) as total_quantity, MIN(invoice_transactions.date) as min_date')
            ->first();

        $weekOfCover = null;
        if ($result && $result->total_quantity > 0) {
            $seconds     = now()->diffInSeconds(Carbon::parse($result->min_date));
            $weekOfCover = ($totalQuantityAvailable * ($seconds / (7.0 * 86400))) / $result->total_quantity;
        }

        $orgStockFamily->stats->update([
            'week_of_cover' => $weekOfCover,
        ]);
    }
}
