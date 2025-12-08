<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Nov 2023 10:49:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

trait WithProspectPrepareForValidation
{
    public function prepareForValidation(): void
    {
        if ($this->has('address') and ! array_filter($this->get('address'))) {
            $this->set('address', null);
        }

        if ($this->has('contact_name') and $this->get('contact_name', '') == '') {
            $this->fill(['contact_name' => null]);
        }

        if ($this->has('company_name') and $this->get('company_name', '') == '') {
            $this->fill(['company_name' => null]);
        }

        if ($this->has('phone') and $this->get('phone', '') == '') {
            $this->fill(
                [
                    'phone' => null,
                ]
            );
        }

        if ($this->has('contact_website')) {
            $contactWebsite = $this->get('contact_website', '');

            if ($contactWebsite == '') {
                $contactWebsite = null;
            } else {
                if (preg_match('/^https?:\/\//', $contactWebsite) === 0) {
                    $contactWebsite = 'https://'.$contactWebsite;
                }
            }
            $this->fill(
                [
                    'contact_website' => $contactWebsite,
                ]
            );
        }
    }
}
