<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import Icon from "@/Components/Icon.vue"
import SupplierAgreementStatusBadge from "@/Components/Procurement/SupplierAgreementStatusBadge.vue"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPersonDolly, faPeopleArrows, faArchive } from "@fal"

library.add(faPersonDolly, faPeopleArrows, faArchive)

interface Supplier {
    id: number
    slug: string
    code: string
    name: string
    location: object
    status_icon: object
    number_supplier_products: number
    number_purchase_orders: number
    number_stock_deliveries: number
    agreement: { status: string, label: string, colour: string, icon: string } | null
}

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

function supplierRoute(supplier: Supplier) {
    return route("grp.majordomo.redirect_supplier", [supplier.id])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: supplier }">
            <Icon :data="supplier.status_icon" />
        </template>
        <template #cell(code)="{ item: supplier }">
            <Link :href="supplierRoute(supplier)" class="primaryLink">
                {{ supplier["code"] }}
            </Link>
            <span v-if="supplier.open_incidents" class="ml-1 rounded-full bg-gray-800 px-1.5 py-0.5 text-xs font-medium text-white tabular-nums" :title="ctrans('Open incidents')">{{ supplier.open_incidents }}</span>
        </template>
        <template #cell(location)="{ item: supplier }">
            <AddressLocation :data="supplier['location']" />
        </template>
        <template #cell(number_supplier_products)="{ item: supplier }">
            {{ locale.number(supplier.number_supplier_products) }}
        </template>
        <template #cell(number_purchase_orders)="{ item: supplier }">
            {{ locale.number(supplier.number_purchase_orders) }}
        </template>
        <template #cell(number_stock_deliveries)="{ item: supplier }">
            {{ locale.number(supplier.number_stock_deliveries) }}
        </template>
        <template #cell(agreement)="{ item: supplier }">
            <SupplierAgreementStatusBadge v-if="supplier.agreement" :status="supplier.agreement" />
        </template>
    </Table>
</template>
