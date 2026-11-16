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
    name_source: "company" | "contact" | null
    nickname: string | null
    agent_slug: string | null
    agent_code: string | null
    agent_name: string | null
    location: object
    status_icon: object
    number_supplier_products: number
    number_purchase_orders: number
    number_open_purchase_orders: number
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

function supplierProductsRoute(supplier: Supplier) {
    return route("grp.supply-chain.suppliers.supplier_products.index", [supplier.slug])
}

function purchaseOrdersRoute(supplier: Supplier) {
    return route("grp.supply-chain.suppliers.purchase_orders.index", [supplier.slug])
}

function nameSourceTooltip(supplier: Supplier): string | null {
    if (supplier.name_source === "company") {
        return ctrans("Company name")
    }

    return supplier.name_source === "contact" ? ctrans("Contact name") : null
}

function agentRoute(supplier: Supplier) {
    return route("grp.supply-chain.agents.show", [supplier.agent_slug])
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
        <template #cell(name)="{ item: supplier }">
            <span v-tooltip="nameSourceTooltip(supplier)">{{ supplier.name }}</span>
        </template>
        <template #cell(agent_code)="{ item: supplier }">
            <Link v-if="supplier.agent_slug" :href="agentRoute(supplier)" class="secondaryLink" :title="supplier.agent_name">
                {{ supplier.agent_code }}
            </Link>
        </template>
        <template #cell(location)="{ item: supplier }">
            <AddressLocation :data="supplier['location']" />
        </template>
        <template #cell(number_supplier_products)="{ item: supplier }">
            <Link :href="supplierProductsRoute(supplier)" class="secondaryLink">
                {{ locale.number(supplier.number_supplier_products) }}
            </Link>
        </template>
        <template #cell(number_purchase_orders)="{ item: supplier }">
            <Link :href="purchaseOrdersRoute(supplier)" class="secondaryLink">
                {{ locale.number(supplier.number_purchase_orders) }}
            </Link>
            <span v-if="supplier.number_open_purchase_orders" class="ml-1 text-xs text-gray-500 tabular-nums" :title="ctrans('Open purchase orders')">
                ({{ ctrans(":count open", { count: locale.number(supplier.number_open_purchase_orders) }) }})
            </span>
        </template>
        <template #cell(number_stock_deliveries)="{ item: supplier }">
            {{ locale.number(supplier.number_stock_deliveries) }}
        </template>
        <template #cell(agreement)="{ item: supplier }">
            <SupplierAgreementStatusBadge v-if="supplier.agreement" :status="supplier.agreement" />
        </template>
    </Table>
</template>
