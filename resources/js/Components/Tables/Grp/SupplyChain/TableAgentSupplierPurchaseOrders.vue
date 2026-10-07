<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import Icon from '@/Components/Icon.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSeedling, faPaperPlane, faSpellCheck, faBoxCheck, faTruck, faClipboardCheck, faExclamationCircle } from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useLocaleStore } from '@/Stores/locale'

library.add(faSeedling, faPaperPlane, faSpellCheck, faBoxCheck, faTruck, faClipboardCheck, faExclamationCircle)

const props = defineProps<{
    data: object,
    tab?: string
}>()

const locale = useLocaleStore()
const isOrganisationRoute = !!route().current()?.startsWith('grp.org.')
const organisationSlug = route().params.organisation

function aspoRoute(aspo: { slug: string }) {
    if (isOrganisationRoute) {
        return route('grp.org.procurement.agent_supplier_purchase_orders.show', [organisationSlug, aspo.slug])
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
            <Link v-if="aspo.supplier_slug && !isOrganisationRoute" :href="route('grp.supply-chain.suppliers.show', [aspo.supplier_slug])" class="secondaryLink">
                {{ aspo.supplier_code }}
            </Link>
            <span v-else>{{ aspo.supplier_code }}</span>
        </template>
        <template #cell(state)="{ item: aspo }">
            <Icon :data="aspo.state_icon" />
        </template>
        <template #cell(delivery_state)="{ item: aspo }">
            <Icon :data="aspo.delivery_state_icon" />
        </template>
        <template #cell(date)="{ item: aspo }">
            {{ useFormatTime(aspo.date) }}
        </template>
        <template #cell(cost_total)="{ item: aspo }">
            {{ locale.currencyFormat(aspo.currency_code, aspo.cost_total) }}
        </template>
        <template #cell(deposit_amount)="{ item: aspo }">
            {{ aspo.deposit_amount != null ? locale.currencyFormat(aspo.currency_code, aspo.deposit_amount) : '-' }}
        </template>
        <template #cell(estimated_received_at)="{ item: aspo }">
            <span :class="aspo.is_overdue ? 'text-red-600 font-medium' : ''">
                {{ aspo.estimated_received_at ? useFormatTime(aspo.estimated_received_at) : '-' }}
            </span>
        </template>
    </Table>
</template>
