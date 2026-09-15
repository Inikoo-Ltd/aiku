<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\TrafficSource;

use App\Enums\EnumHelperTrait;

/**
 * The kinds of change Aiku can propose to an advertising account.
 *
 * Every one maps onto an action that already exists and already validates, confirms and audits its
 * own work, which is what makes a proposal safe to approve: approving does not hand a language model
 * the keys to the API, it fills in the arguments of a form a person could have filled in themselves.
 *
 * A type is only worth adding here once the action behind it exists. Budget changes are deliberately
 * absent: they are decided on return on ad spend, and that reads from conversion value, which several
 * accounts record as zero. A proposal nobody can trust is worse than no proposal.
 */
enum AdProposalTypeEnum: string
{
    use EnumHelperTrait;

    case EXCLUDE_SEARCH_TERM = 'exclude_search_term';
    case PAUSE_KEYWORD = 'pause_keyword';
    case ADD_SEARCH_TERM_KEYWORD = 'add_search_term_keyword';
    case ADD_DEMAND_KEYWORD = 'add_demand_keyword';
    case STRENGTHEN_AD = 'strengthen_ad';
    case NEW_CAMPAIGN = 'new_campaign';

    public static function labels(): array
    {
        return [
            self::EXCLUDE_SEARCH_TERM->value     => 'Stop paying for a search',
            self::PAUSE_KEYWORD->value           => 'Pause a keyword',
            self::ADD_SEARCH_TERM_KEYWORD->value => 'Bid on a search that converts',
            self::ADD_DEMAND_KEYWORD->value      => 'Bid on what your site visitors look for',
            self::STRENGTHEN_AD->value           => 'Give an ad more to work with',
            self::NEW_CAMPAIGN->value            => 'Advertise something you sell and do not advertise',
        ];
    }

    /**
     * Whether this kind of proposal is generated at all.
     *
     * The two that cut spending are off, and the code is kept rather than deleted because the rules
     * behind them are sound. What is not sound is the judgement in front of them. Asked to sort a
     * wasteful search from a valuable one, the model first refused to exclude anything, and then after
     * the prompt was tightened wanted to exclude `uk dropshipping suppliers` from a wholesale
     * dropshipping supplier, on the grounds that it "targets suppliers". Both answers were argued
     * confidently and one of them would have cost the account its best terms.
     *
     * The missing piece is not wording. Nobody has written down what each shop is and who it sells to,
     * so the model is reasoning from a list of product names. These switch back on once that exists
     * and once conversion tracking records a value, which is what "no sale" currently fails to mean.
     *
     * Adding a keyword fails cheaply by comparison: a few pounds, undone in one click. That asymmetry
     * is the whole reason for this flag.
     */
    public function isEnabled(): bool
    {
        return !$this->savesMoney();
    }

    /** Cuts spending versus commits to more of it: the page groups them, because the risk differs. */
    public function savesMoney(): bool
    {
        return in_array($this, [self::EXCLUDE_SEARCH_TERM, self::PAUSE_KEYWORD], true);
    }

    /**
     * Whether a person has to say where a new keyword goes. Both add types create something that
     * starts spending, and a campaign with several ad groups gives no obvious home for it.
     */
    public function needsAdGroup(): bool
    {
        return in_array($this, [self::ADD_SEARCH_TERM_KEYWORD, self::ADD_DEMAND_KEYWORD], true);
    }

    /**
     * Whether approving this finishes the job, or hands the marketer a part-filled form.
     *
     * A new campaign needs a budget, targeting, an ad group, keywords and an ad, and no rule should
     * settle all of that on somebody's behalf. That one opens the campaign form with what is known
     * already filled in, and a person completes it. Everything else applies on approval.
     */
    public function isDraft(): bool
    {
        return $this === self::NEW_CAMPAIGN;
    }
}
