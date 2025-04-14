<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useFormatTime } from '@/Composables/useFormatTime'
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale";

const props = defineProps<{
    data: TableTS,
}>()

function portfolioRoute(portfolio: {}) {
    switch (route().current()) {
         case "grp.org.fulfilments.show.crm.customers.show.platforms.show.portfolios.index":
            return route(
               "grp.org.fulfilments.show.crm.customers.show.platforms.show.portfolios.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"],  route().params["customerHasPlatform"],  portfolio.slug])
    }
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: portfolio }">
            <Link :href="portfolioRoute(portfolio)" class="primaryLink">
                {{ portfolio["reference"] }}
            </Link>
        </template>
    </Table>
</template>
