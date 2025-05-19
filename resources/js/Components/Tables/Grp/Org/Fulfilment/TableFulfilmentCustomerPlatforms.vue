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

const locale = useLocaleStore();
const layout = inject('layout', layoutStructure)

function platformRoute(platform: {}) {
    switch (route().current()) {
         case "grp.org.fulfilments.show.crm.customers.show.platforms.index":
            return route(
               "grp.org.fulfilments.show.crm.customers.show.platforms.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"], platform.customer_has_platform_slug])
    }
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: platform }">
            <Link :href="platformRoute(platform)" class="primaryLink">
                {{ platform["reference"] }}
            </Link>
        </template>
    </Table>
</template>
