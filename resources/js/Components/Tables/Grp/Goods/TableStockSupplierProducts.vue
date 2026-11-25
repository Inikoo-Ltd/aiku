<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Oct 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrophy as falTrophy, faSnooze } from "@fal"
import { faTrophy as fasTrophy } from "@fas"

library.add(falTrophy, faSnooze, fasTrophy)

defineProps<{
    data: object
    tab?: string
    canSetPreferred?: boolean
}>()

const locale = useLocaleStore()

function setPreferred(supplierProduct: { stock_id: number, supplier_product_id: number }) {
    router.patch(
        route("grp.models.stock.supplier_product.set_preferred", [supplierProduct.stock_id, supplierProduct.supplier_product_id]),
        {},
        { preserveScroll: true }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(preferred)="{ item: supplierProduct }">
            <Icon
                v-if="supplierProduct.is_preferred"
                :data="{ icon: 'fas fa-trophy', class: 'text-amber-500', tooltip: ctrans('Preferred supplier in every organisation') }" />
            <Icon
                v-else
                :data="{ icon: 'fal fa-snooze', class: 'text-gray-400', tooltip: ctrans('Backup supplier') }" />
        </template>

        <template #cell(supplier_name)="{ item: supplierProduct }">
            <Link
                v-if="supplierProduct.supplier_slug"
                :href="route('grp.supply-chain.suppliers.show', [supplierProduct.supplier_slug])"
                class="primaryLink">
                {{ supplierProduct.supplier_name }}
            </Link>
            <span v-else>{{ supplierProduct.supplier_name }}</span>
        </template>

        <template #cell(agent_name)="{ item: supplierProduct }">
            <Link
                v-if="supplierProduct.agent_slug"
                :href="route('grp.supply-chain.agents.show', [supplierProduct.agent_slug])"
                class="secondaryLink">
                {{ supplierProduct.agent_name }}
            </Link>
            <span v-else class="text-gray-300">—</span>
        </template>

        <template #cell(unit_cost)="{ item: supplierProduct }">
            {{ locale.currencyFormat(supplierProduct.currency_code, supplierProduct.unit_cost) }}
        </template>

        <template #cell(preferred_in)="{ item: supplierProduct }">
            <span v-if="supplierProduct.preferred_in">{{ supplierProduct.preferred_in }}</span>
            <span v-else class="text-gray-300">—</span>
        </template>

        <template #cell(set_preferred)="{ item: supplierProduct }">
            <button
                v-if="canSetPreferred && !supplierProduct.is_preferred"
                type="button"
                class="inline-flex items-center gap-1 text-gray-400 hover:text-amber-500"
                v-tooltip="ctrans('Set as preferred supplier in every organisation')"
                @click="setPreferred(supplierProduct)">
                <span class="text-xs whitespace-nowrap">{{ ctrans("Set as") }}</span>
                <Icon :data="{ icon: 'fal fa-trophy' }" />
            </button>
        </template>
    </Table>
</template>
