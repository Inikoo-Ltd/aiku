<?php

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockHydrateWeekOfCover implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-week-of-cover {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $result = DB::table('invoice_transactions')
            ->join('invoice_transaction_has_org_stocks', 'invoice_transaction_has_org_stocks.invoice_transaction_id', '=', 'invoice_transactions.id')
            ->where('invoice_transaction_has_org_stocks.org_stock_id', $orgStock->id)
            ->whereNull('invoice_transactions.deleted_at')
            ->selectRaw('COALESCE(SUM(invoice_transactions.quantity), 0) as total_quantity, MIN(invoice_transactions.date) as min_date')
            ->first();

        $weekOfCover = null;
        if ($result && $result->total_quantity > 0) {
            $seconds     = now()->diffInSeconds(Carbon::parse($result->min_date));
            $weekOfCover = ($orgStock->quantity_available * ($seconds / (7.0 * 86400))) / $result->total_quantity;
        }

        $orgStock->stats->update([
            'week_of_cover' => $weekOfCover,
        ]);
    }
}
