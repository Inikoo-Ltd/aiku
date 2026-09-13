<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One search box, GitHub style: free words match the weighted full text index (reference and subject first,
 * then description, then comments, then the people on the ticket) and key:value tokens narrow the set.
 *
 *   email marketing status:open assignee:raul -slack "exact phrase" after:2026-09-01
 *
 * Keys: status priority module kind type assignee reporter customer tag is after before
 * Values: me, none, a comma list, or a partial name. is: confidential unassigned mine rated
 */
class ApplyTicketSearch
{
    use AsAction;

    public const array HELP = ['1223', 'help-1223', 'status:open', 'assignee:me', 'reporter:aimee', 'module:ordering', 'priority:urgent', 'tag:invoice', 'is:unassigned', 'after:2026-09-01', '-word', '"exact phrase"'];

    private const array ENUM_COLUMNS = [
        'status'   => TicketStatusEnum::class,
        'priority' => ChatPriorityEnum::class,
        'module'   => TicketModuleEnum::class,
        'kind'     => TicketKindEnum::class,
        'type'     => TicketTypeEnum::class,
    ];

    /**
     * Applies the search to $query and returns whether free text was matched (then search_rank and search_snippet are selected).
     */
    public function handle($query, string $search, User $user): bool
    {
        if ($references = $this->references($search)) {
            $query->whereIn('tickets.reference', $references);

            return false;
        }

        preg_match_all('/(-)?(?:([a-z]+):)?(?:"([^"]*)"|(\S+))/iu', $search, $tokens, PREG_SET_ORDER);

        $terms = [];
        foreach ($tokens as $token) {
            $negated = $token[1] === '-';
            $key     = strtolower($token[2] ?? '');
            $value   = $token[3] !== '' ? $token[3] : ($token[4] ?? '');

            if ($key !== '' && $this->applyFilter($query, $key, $value, $negated, $user)) {
                continue;
            }
            if ($lexemes = $this->lexemes($key !== '' ? "$key:$value" : $value)) {
                $terms[] = ($negated ? '!' : '').'('.implode(' <-> ', $lexemes).')';
            }
        }

        if ($terms === []) {
            return false;
        }

        $tsQuery = implode(' & ', $terms);
        $vector  = Ticket::canBeAssignedBy($user) ? "(tickets.search_vector || coalesce(tickets.internal_search_vector, ''))" : 'tickets.search_vector';
        $comments = 'SELECT string_agg(c.body, \' \') FROM ticket_comments c WHERE c.ticket_id = tickets.id'.(Ticket::canBeAssignedBy($user) ? '' : ' AND NOT c.is_internal');

        $query
            ->whereRaw("$vector @@ to_tsquery('english', ?)", [$tsQuery])
            ->selectRaw("ts_rank($vector, to_tsquery('english', ?)) AS search_rank", [$tsQuery])
            ->selectRaw(
                "ts_headline('english', concat_ws(' ', tickets.subject, tickets.description, ($comments)), to_tsquery('english', ?), 'StartSel=[[, StopSel=]], MaxFragments=2, MaxWords=14, MinWords=6, FragmentDelimiter=~~') AS search_snippet",
                [$tsQuery]
            );

        return true;
    }

    /**
     * A ticket number is the search people do most, so 1223, help-1223, HELP1223 and a pasted ticket URL
     * all go straight to the unique reference index instead of the text search.
     *
     * @return array<int, string>
     */
    private function references(string $search): array
    {
        $search = trim(preg_replace('/[?#].*$/', '', trim($search)), " /\t");
        if (preg_match('~(?:^|/)([a-z]+)-?(\d+)$~i', $search, $match)) {
            return [strtoupper($match[1]).'-'.$match[2]];
        }
        if (preg_match('/^\d+$/', $search)) {
            return array_map(fn (TicketTypeEnum $type) => $type->prefix().'-'.$search, TicketTypeEnum::cases());
        }

        return [];
    }

    /**
     * Prefix match on every word, so "marke" finds marketing. English stemming drops stopwords, a phrase like "not working" matches on working alone.
     *
     * @return array<int, string>
     */
    private function lexemes(string $value): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_map(fn (string $word) => "$word:*", $words);
    }

    private function applyFilter($query, string $key, string $value, bool $negated, User $user): bool
    {
        $values = array_filter(array_map('trim', explode(',', $value)));
        $not    = $negated ? 'Not' : '';

        if (isset(self::ENUM_COLUMNS[$key])) {
            $enum   = self::ENUM_COLUMNS[$key];
            $labels = array_map('strtolower', $enum::labels());
            $values = array_map(fn ($value) => array_search(strtolower($value), $labels, true) ?: strtolower($value), $values);
            $query->{"where{$not}In"}("tickets.$key", $values);

            return true;
        }

        return match ($key) {
            'assignee' => $this->wherePerson($query, 'tickets.assignee_id', $values, $negated, $user),
            'reporter' => $this->whereReporter($query, $values, $negated, $user),
            'customer' => (bool) $query->{"where{$not}In"}('tickets.customer_id', fn ($sub) => $sub->select('id')->from('customers')->where(fn ($q) => $this->orIlike($q, ['name', 'contact_name'], $values))),
            'tag'      => (bool) $query->where(fn ($q) => array_walk($values, fn ($tag) => $q->{$negated ? 'whereJsonDoesntContain' : 'orWhereJsonContains'}('tickets.tags', $tag))),
            'is'       => $this->whereIs($query, $values, $negated, $user),
            'after', 'since' => (bool) $query->where('tickets.created_at', $negated ? '<' : '>=', Carbon::parse($value)->startOfDay()),
            'before'   => (bool) $query->where('tickets.created_at', $negated ? '>=' : '<', Carbon::parse($value)->startOfDay()),
            default    => false,
        };
    }

    private function wherePerson($query, string $column, array $values, bool $negated, User $user): bool
    {
        $not = $negated ? 'Not' : '';
        if ($values === ['me']) {
            $query->{"where$not"}($column, $user->id);
        } elseif ($values === ['none']) {
            $query->{$negated ? 'whereNotNull' : 'whereNull'}($column);
        } else {
            $query->{"where{$not}In"}($column, fn ($sub) => $sub->select('id')->from('users')->where(fn ($q) => $this->orIlike($q, ['username', 'contact_name'], $values)));
        }

        return true;
    }

    private function whereReporter($query, array $values, bool $negated, User $user): bool
    {
        if ($values === ['me']) {
            $query->{$negated ? 'whereNot' : 'where'}(fn ($q) => $q->where('tickets.reporter_type', 'User')->where('tickets.reporter_id', $user->id));

            return true;
        }

        $query->{$negated ? 'whereNot' : 'where'}(function ($q) use ($values) {
            $q->where(fn ($q) => $q->where('tickets.reporter_type', 'User')->whereIn('tickets.reporter_id', fn ($sub) => $sub->select('id')->from('users')->where(fn ($q) => $this->orIlike($q, ['username', 'contact_name'], $values))))
                ->orWhereIn('tickets.customer_id', fn ($sub) => $sub->select('id')->from('customers')->where(fn ($q) => $this->orIlike($q, ['name', 'contact_name'], $values)));
        });

        return true;
    }

    private function whereIs($query, array $values, bool $negated, User $user): bool
    {
        foreach ($values as $flag) {
            match ($flag) {
                'confidential' => $query->where('tickets.is_confidential', !$negated),
                'unassigned'   => $query->{$negated ? 'whereNotNull' : 'whereNull'}('tickets.assignee_id'),
                'rated'        => $query->{$negated ? 'whereNull' : 'whereNotNull'}('tickets.rated_at'),
                'mine'         => $query->{$negated ? 'whereNot' : 'where'}(fn ($q) => $q->where('tickets.assignee_id', $user->id)->orWhere(fn ($q) => $q->where('tickets.reporter_type', 'User')->where('tickets.reporter_id', $user->id))),
                default        => null,
            };
        }

        return true;
    }

    private function orIlike($query, array $columns, array $values): void
    {
        foreach ($values as $value) {
            foreach ($columns as $column) {
                $query->orWhereRaw("$column COLLATE \"C\" ILIKE ?", ['%'.str_replace(['%', '_'], ['\%', '\_'], $value).'%']);
            }
        }
    }
}
