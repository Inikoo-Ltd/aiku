<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierSubmission;

use App\Enums\EnumHelperTrait;

enum SupplierSubmissionTypeEnum: string
{
    use EnumHelperTrait;

    case COMPANY_DETAILS = 'company_details';
    case PHOTOS = 'photos';
    case REFERRAL = 'referral';
    case PRODUCT_PROPOSAL = 'product_proposal';
    case COMPLIANCE_DOCUMENT = 'compliance_document';
    case PRICE_CHANGE = 'price_change';
    case CONTACT = 'contact';

    public static function labels(): array
    {
        return [
            'company_details'     => __('Company details'),
            'photos'              => __('Product pictures'),
            'referral'            => __('Customer referral'),
            'product_proposal'    => __('New product idea'),
            'compliance_document' => __('Compliance document'),
            'price_change'        => __('Price change request'),
            'contact'             => __('New contact'),
        ];
    }
}
