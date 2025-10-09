<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Order } from "@/types/order"
import type { Links, Meta } from "@/types/Table"
import { useFormatTime } from '@/Composables/useFormatTime'
import Icon from "@/Components/Icon.vue"

import { faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
library.add(faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle)

defineProps<{
    data: {
        data: {}[]
        links: Links
        meta: Meta
    },
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)

function purgeRoute(purge: {}) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.overview.ordering.purges.index":
            return route(
                "grp.org.shops.show.ordering.purges.show",
                [purge.organisation_name, purge.shop_name, purge.id])
        case "grp.org.shops.show.ordering.purges.index":
            return route(
                "grp.org.shops.show.ordering.purges.show",
                [route().params["organisation"], route().params["shop"], purge.id])
        default:
            return ''
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(scheduled_at)="{ item: purge }">
            <Link :href="purgeRoute(purge)" class="primaryLink">
                <span v-if="purge.scheduled_at">{{ purge["scheduled_at"] }}</span>
                <span v-else class="opacity-70 italic">
                    {{ trans("No date") }}
                </span>
            </Link>
        </template>

        <template #cell(estimated_net_amount)="{ item }">
            {{ locale.currencyFormat(item.currency_code, item.estimated_net_amount) }}
        </template>
    </Table>
</template>
