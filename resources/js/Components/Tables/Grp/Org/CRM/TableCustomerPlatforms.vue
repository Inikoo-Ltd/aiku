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
         case "grp.org.shops.show.crm.customers.show.platforms.index":
            return route(
               "grp.org.shops.show.crm.customers.show.platforms.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], platform.customer_has_platform_id])
    }
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(code)="{ item: platform }">
            <Link :href="platformRoute(platform)" class="primaryLink">
                {{ platform["code"] }}
            </Link>
        </template>
    </Table>
</template>
