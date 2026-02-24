<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from "vue"
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const locale = inject("locale", aikuLocaleStructure)

const props = defineProps<{
    data: {}
    tab?: string
}>()

function platformRoute(platform: any) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.platforms.index":
            return route(
                "grp.org.shops.show.crm.platforms.show",
                [route().params['organisation'], route().params['shop'], platform.slug]
            )
        case "grp.platforms.index":
            return route(
                "grp.platforms.show",
                [platform.slug]
            )
        default:
            return ""
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
         <template #cell(name)="{ item: platform }">
            <Link :href="platformRoute(platform)" class="primaryLink">
                {{ platform.name }}
            </Link>
        </template>

        <!-- <template #cell(number_customer_sales_channels)="{ item: platform }">
            <span v-if="platform.number_customer_sales_channel_broken === 0 && platform.number_customer_sales_channels === 0">
                {{ platform.number_customer_sales_channels }}
            </span>
            <span v-else-if="platform.number_customer_sales_channel_broken === platform.number_customer_sales_channels" class="text-red-500">
                {{ platform.number_customer_sales_channel_broken }}
            </span>
            <span v-else>
                <span class="text-red-500">{{ platform.number_customer_sales_channel_broken }}</span>/{{ platform.number_customer_sales_channels }}
            </span>
        </template> -->

        <template #cell(sales)="{ item: platform }">
            {{ locale.currencyFormat('GBP', platform.sales) }}
        </template>
    </Table>
</template>
