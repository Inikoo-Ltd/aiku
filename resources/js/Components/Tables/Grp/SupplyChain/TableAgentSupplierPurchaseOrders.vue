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
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: aspo }">
            <Link :href="route('grp.supply-chain.agent_supplier_purchase_orders.show', [aspo.slug])" class="primaryLink">
                {{ aspo.reference }}
            </Link>
        </template>
        <template #cell(supplier_code)="{ item: aspo }">
            <Link v-if="aspo.supplier_slug" :href="route('grp.supply-chain.suppliers.show', [aspo.supplier_slug])" class="secondaryLink">
                {{ aspo.supplier_code }}
            </Link>
        </template>
        <template #cell(date)="{ item: aspo }">
            {{ useFormatTime(aspo.date) }}
        </template>
        <template #cell(cost_total)="{ item: aspo }">
            {{ aspo.cost_total }} {{ aspo.currency_code }}
        </template>
    </Table>
</template>
