<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\ReceiveTrafficSourceCostWebhook;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mints, lists and revokes the tokens ad-platform scripts use to post spend.
 *
 * ponytail: Sanctum's personal access tokens instead of a bespoke tokens table. It already gives
 * hashed-at-rest storage, a name per holder, individual revocation and, because the token belongs to
 * the shop, per-shop scoping with no extra column. A new table would only re-implement all of that.
 */
class TrafficSourceCostToken extends Command
{
    protected $signature = 'traffic-source:cost-token
                           {shop? : Shop slug}
                           {name? : Who the token is for, e.g. "AW Advantage"}
                           {--list : List the tokens of the shop}
                           {--revoke= : Revoke the token with this id}';

    protected $description = 'Manage the tokens that let an ad platform script post advertising spend';

    public function handle(): int
    {
        if ($tokenId = $this->option('revoke')) {
            $deleted = PersonalAccessToken::where('id', $tokenId)
                ->where('tokenable_type', (new Shop())->getMorphClass())
                ->delete();

            $this->info($deleted ? "Token {$tokenId} revoked." : "No cost token with id {$tokenId}.");

            return $deleted ? Command::SUCCESS : Command::FAILURE;
        }

        $shop = Shop::where('slug', (string) $this->argument('shop'))->first();

        if (!$shop) {
            $this->error("Unknown shop '{$this->argument('shop')}'.");

            return Command::FAILURE;
        }

        if ($this->option('list')) {
            $this->table(
                ['id', 'name', 'last used'],
                $shop->tokens()->get()->map(fn ($token) => [
                    $token->id,
                    $token->name,
                    $token->last_used_at?->toDateTimeString() ?? 'never',
                ])
            );

            return Command::SUCCESS;
        }

        $name = (string) $this->argument('name');

        if ($name === '') {
            $this->error('A name is required so a leaked token can be identified and revoked on its own.');

            return Command::FAILURE;
        }

        $token = $shop->createToken($name, [ReceiveTrafficSourceCostWebhook::ABILITY]);

        $this->newLine();
        $this->info("Token for '{$name}' on shop '{$shop->slug}' (shown once):");
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line('Endpoint: '.route('webhooks.traffic_source_costs'));

        return Command::SUCCESS;
    }
}
