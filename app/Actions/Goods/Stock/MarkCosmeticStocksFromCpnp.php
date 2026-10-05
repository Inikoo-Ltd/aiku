<?php

namespace App\Actions\Goods\Stock;

use App\Models\Goods\Stock;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A stock made of a trade unit with a CPNP number is a cosmetic. Only ever switches the flag on,
 * so a stock somebody has marked by hand keeps its flag.
 */
class MarkCosmeticStocksFromCpnp
{
    use AsAction;

    public string $commandSignature = 'stocks:mark_cosmetic_from_cpnp
        {--apply : Mark the stocks, without this the command only lists what it would mark}';

    public function handle(): int
    {
        return $this->stocksToMark()->get()->each(fn (Stock $stock) => $stock->update(['is_cosmetic' => true]))->count();
    }

    public function stocksToMark(): Builder
    {
        return Stock::query()
            ->where('is_cosmetic', false)
            ->whereHas('tradeUnits', fn ($query) => $query->whereNotNull('cpnp_number')->where('cpnp_number', '!=', ''));
    }

    public function asCommand(Command $command): int
    {
        if ($command->option('apply')) {
            $command->info($this->handle().' stocks marked as cosmetic');

            return 0;
        }

        $stocks = $this->stocksToMark()->orderBy('code')->get(['code', 'name']);
        $stocks->each(fn (Stock $stock) => $command->line("$stock->code  $stock->name"));
        $command->info($stocks->count().' stocks would be marked as cosmetic, run with --apply to mark them');

        return 0;
    }
}
