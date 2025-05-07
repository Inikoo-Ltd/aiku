<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { PaymentAccount } from "@/types/payment-account"
import { faBox, faHandHoldingBox, faPallet, faPencil } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Shop } from '@/types/shop'
import { useLocaleStore } from '@/Stores/locale'
library.add(faBox, faHandHoldingBox, faPallet, faPencil)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

function topUpRoute(topUp: {}) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.dashboard.payments.accounting.top_ups.index":
            return route(
                "grp.org.shops.show.dashboard.payments.accounting.top_ups.show",
                [route().params["organisation"], route().params["shop"], topUp.slug])
        default:
            return null
    }
}

</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: topUp }">
            <Link :href="topUpRoute(topUp)" class="primaryLink">
                {{ topUp["reference"] }}
            </Link>
        </template>
    </Table>
</template>
