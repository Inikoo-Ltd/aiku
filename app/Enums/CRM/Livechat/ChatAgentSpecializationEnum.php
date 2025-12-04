<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatAgentSpecializationEnum: string
{
    use EnumHelperTrait;
    case BILLING = 'billing';
    case TECHNICAL = 'technical';
    case SALES = 'sales';
    case SUPPORT = 'support';
    case ONBOARDING = 'onboarding';
    case COMPLAINT = 'complaint';
    case FEEDBACK = 'feedback';
    case GENERAL = 'general';

    public static function labels(): array
    {
        return [
            'billing' => __('Billing & Payments'),
            'technical' => __('Technical Support'),
            'sales' => __('Sales & Pre-sales'),
            'support' => __('Customer Support'),
            'onboarding' => __('Onboarding'),
            'complaint' => __('Complaint Handling'),
            'feedback' => __('Feedback Collection'),
            'general' => __('General Inquiry'),
        ];
    }


    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'label' => self::labels()[$case->value] ?? $case->value,
                'value' => $case->value,
            ])
            ->values()
            ->toArray();
    }
}
