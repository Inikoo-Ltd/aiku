<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { useFormatTime } from '@/Composables/useFormatTime'

const props = defineProps<{
    data: object,
    tab?: string
}>()

function aspoRoute(aspo: { slug: string }) {
    if (route().current()?.startsWith('grp.org.')) {
        return route('grp.org.procurement.agent_supplier_purchase_orders.show', [route().params.organisation, aspo.slug])
    }
    return route('grp.supply-chain.agent_supplier_purchase_orders.show', [aspo.slug])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: aspo }">
            <Link :href="aspoRoute(aspo)" class="primaryLink">
                {{ aspo.reference }}
            </Link>
        </template>
        <template #cell(supplier_code)="{ item: aspo }">
            <Link v-if="aspo.supplier_slug && !route().current()?.startsWith('grp.org.')" :href="route('grp.supply-chain.suppliers.show', [aspo.supplier_slug])" class="secondaryLink">
                {{ aspo.supplier_code }}
            </Link>
            <span v-else>{{ aspo.supplier_code }}</span>
        </template>
        <template #cell(date)="{ item: aspo }">
            {{ useFormatTime(aspo.date) }}
        </template>
        <template #cell(cost_total)="{ item: aspo }">
            {{ aspo.cost_total }} {{ aspo.currency_code }}
        </template>
    </Table>
</template>
