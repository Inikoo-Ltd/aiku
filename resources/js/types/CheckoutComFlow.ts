/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Mar 2023 00:11:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

export interface CheckoutComFlow {
    label: string;
    key: string;
    public_key: string;
    environment: "production" | "sandbox";
    locale: string;
    icon: string;
    data: any; // The payment session data from Checkout.com
}
