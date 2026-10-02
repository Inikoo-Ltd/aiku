<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jev reads a ticket's subject and description and fills in its kind and module when nobody
 * did, so the reports can show which parts of aiku the tickets are about over time. What a
 * person set is never changed, and a guess Jev is not sure of is left empty.
 */
class ClassifyTicket
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public int $jobTries = 2;

    public string $commandSignature = 'tickets:classify {--limit= : most tickets to classify}';

    private const float MIN_CONFIDENCE = 0.5;

    private const array KINDS = [
        'escalation'     => 'a customer problem passed on by customer service for engineers to look at',
        'bug'            => 'something in aiku or the websites that worked or should work and does not: an error, a crash, wrong numbers, a page not loading',
        'feature'        => 'asking for something new or for aiku to work differently: a new report, column, button, option or workflow',
        'task'           => 'engineering work between engineers: upgrades, refactors, infrastructure',
        'qa'             => 'asking QA to check a change',
        'documentation'  => 'asking how something works or for help, guides or documents',
        'data_integrity' => 'wrong or missing data to correct: a product, stock, price, order or customer record that is wrong, duplicated or missing',
        'aurora'         => 'about Aurora, the old system aiku replaces: data not transferred or fetched from Aurora, Aurora still showing or doing something, moving work off Aurora',
        'support'        => 'asking an engineer to investigate or explain what happened, without a clear bug',
    ];

    private const array MODULES = [
        'accounting'      => 'invoices, payments, refunds, credit, payment providers, VAT, accountants, shipping and other charges billed to customers',
        'products'        => 'products, families, departments, collections, prices, product content, master products, trade units, stocks, barcodes, ingredients, product compliance data',
        'marketing'       => 'emails, mailshots, newsletters, notifications, unsubscribes, offers, vouchers, discounts, gold reward, reviews',
        'websites'        => 'the shop websites customers see: pages, basket, checkout, website search, speed; building them in the workshop: banners, menus, blocks; the customer account area',
        'procurement'     => 'suppliers, agents, purchase orders, supplier products, buying stock, stock deliveries arriving and being received, partners and stock transfers between organisations',
        'system'          => 'users, permissions, roles, logging in to aiku, settings, servers, deployments, outages, scheduled jobs, search inside aiku',
        'crm'             => 'customers, prospects, customer accounts, customer notes, web users, registrations',
        'chat'            => 'the customer chat inbox, WhatsApp, chat agents, AI replies',
        'ordering'        => 'orders, baskets, order lines, submitting and editing orders, order states',
        'dispatching'     => 'delivery notes, picking, packing, shipping, couriers, labels, parcels',
        'inventory'       => 'warehouses, locations, stock levels, stock movements, stock takes',
        'dropshipping'    => 'dropshipping customers and their portfolios; connections to outside platforms: Shopify, WooCommerce, Wix, eBay, Amazon, TikTok, Faire, Ankorstore, marketplaces, stock or order sync, product feeds, APIs',
        'fulfilment'      => 'fulfilment customers, pallets, storage, stored items, fulfilment recurring bills',
        'production'      => 'manufacturing, raw materials, production jobs, artisans',
        'human_resources' => 'employees, clocking, timesheets, holidays, payroll, job positions, workplaces',
        'reports'         => 'reports, dashboards, sales figures, statistics, exports',
    ];

    public function handle(Ticket $ticket): Ticket
    {
        $needsKind   = $ticket->kind === null;
        $needsModule = $ticket->module === null;

        if (!$needsKind && !$needsModule) {
            return $ticket;
        }

        $state = [
            'subject'     => mb_substr((string) $ticket->subject, 0, 300),
            'description' => mb_substr(trim(strip_tags((string) $ticket->description)), 0, 3000),
        ];

        $questions = [];
        if ($needsKind) {
            $questions['kind'] = ['type' => 'choice', 'instructions' => 'What kind of ticket is this, raised to the engineers of aiku, the ERP of a wholesale giftware business?', 'criteria' => $this->kinds($ticket)];
        }
        if ($needsModule) {
            $questions['module'] = ['type' => 'choice', 'instructions' => 'Which part of aiku, the ERP of a wholesale giftware business, is this ticket about?', 'criteria' => self::MODULES];
        }

        $answers = AskJev::make()->handle($state, $questions);

        $changes = array_filter([
            'kind'   => $this->confidentChoice($answers, 'kind'),
            'module' => $this->confidentChoice($answers, 'module'),
        ]);

        if ($changes) {
            $ticket->update($changes);
        }

        return $ticket;
    }

    /**
     * @return array<string, string>
     */
    private function kinds(Ticket $ticket): array
    {
        return $ticket->type === TicketTypeEnum::ENGINEER ? self::KINDS : Arr::except(self::KINDS, TicketKindEnum::internalValues());
    }

    private function confidentChoice(?array $answers, string $question): ?string
    {
        $choice     = Arr::get($answers, "$question.choice");
        $confidence = Arr::get($answers, "$question.probabilities.$choice", Arr::get($answers, "$question.confidence"));

        return $choice && is_numeric($confidence) && $confidence >= self::MIN_CONFIDENCE ? $choice : null;
    }

    public function asCommand(Command $command): int
    {
        $query = Ticket::where(fn ($query) => $query->whereNull('kind')->orWhereNull('module'))->orderByDesc('id');
        if ($command->option('limit')) {
            $query->limit((int) $command->option('limit'));
        }

        $before = ['kind' => 0, 'module' => 0];
        $after  = ['kind' => 0, 'module' => 0];
        $bar    = $command->getOutput()->createProgressBar($query->count());

        $query->get()->each(function (Ticket $ticket) use (&$before, &$after, $bar) {
            $before['kind']   += (int) ($ticket->kind === null);
            $before['module'] += (int) ($ticket->module === null);
            $this->handle($ticket);
            $after['kind']   += (int) ($ticket->kind === null);
            $after['module'] += (int) ($ticket->module === null);
            $bar->advance();
        });

        $bar->finish();
        $command->newLine();
        $command->info(($before['kind'] - $after['kind']).'/'.$before['kind'].' kinds and '.($before['module'] - $after['module']).'/'.$before['module'].' modules filled in');

        return 0;
    }
}
