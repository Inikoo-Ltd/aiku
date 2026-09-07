<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, reactive } from "vue"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"

interface OrgLine {
    id: number
    organisation_code: string
    units: number
    priority: string
    state: string
    dismiss_reason: string | null
    created_at: string
}

interface Product {
    supplier_product_id: number
    code: string
    name: string
    total_units: number
    units_per_carton: number | null
    total_cartons: number | null
    minimum_carton_order: number | null
    moq_progress: number | null
    orgs: OrgLine[]
    oldest_created_at: string
}

interface OpenAgentSupplierPurchaseOrder {
    id: number
    reference: string
    slug: string
}

interface Supplier {
    supplier_id: number
    code: string
    slug: string
    estimated_value: number
    products: Product[]
    open_agent_supplier_purchase_order: OpenAgentSupplierPurchaseOrder | null
}

interface AgentGroup {
    agent_id: number
    code: string
    slug: string
    suppliers: Supplier[]
}

const props = defineProps<{
    agents: AgentGroup[]
    editable?: boolean
    cherryPickRoute?: string
}>()

const selected = reactive<Record<number, number>>({})

const selfRoute = computed(() => ({ name: route().current() as string, parameters: route().params }))

const stats = computed(() => {
    let suppliers = 0
    let lines = 0
    let value = 0

    for (const agentGroup of props.agents) {
        suppliers += agentGroup.suppliers.length
        for (const supplier of agentGroup.suppliers) {
            value += supplier.estimated_value
            for (const product of supplier.products) {
                lines += product.orgs.length
            }
        }
    }

    return [
        { label: trans("Suppliers"), description: "", icon: "fal fa-person-dolly", value: suppliers, tone: "indigo", route: selfRoute.value, metrics: [] },
        { label: trans("Open lines"), description: "", icon: "fal fa-boxes", value: lines, tone: "amber", route: selfRoute.value, metrics: [] },
        { label: trans("Estimated value"), description: "", icon: "fal fa-box-usd", value: Math.round(value), tone: "emerald", route: selfRoute.value, metrics: [] },
    ]
})

function toggle(line: OrgLine) {
    if (line.id in selected) {
        delete selected[line.id]
    } else {
        selected[line.id] = line.units
    }
}

function submitCherryPick() {
    if (!props.cherryPickRoute) return

    const lines = Object.entries(selected).map(([id, quantity_units]) => ({ id: Number(id), quantity_units }))
    router.post(props.cherryPickRoute, { lines }, { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } })
}

function proposeDismiss(line: OrgLine) {
    const reason = window.prompt(trans("Reason for proposing dismissal") + " (" + line.organisation_code + ")")
    if (!reason) return

    router.post(route("grp.org.procurement.shopping_list.propose_dismiss", [route().params["organisation"], line.id]), { dismiss_reason: reason }, { preserveScroll: true })
}
</script>

<template>
    <div class="mx-4 my-4 space-y-8">
        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill v-for="card in stats" :key="card.label" :card="card" />
        </div>

        <div v-if="editable && Object.keys(selected).length" class="sticky top-0 z-10 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
            <span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="submitCherryPick">
                {{ trans("Create purchase orders") }}
            </button>
        </div>

        <div v-for="agentGroup in agents" :key="agentGroup.agent_id">
            <h2 class="text-lg font-semibold">{{ agentGroup.code }}</h2>

            <div v-for="supplier in agentGroup.suppliers" :key="supplier.supplier_id" class="mt-4 rounded-sm border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between font-mono">
                    <h3 class="text-sm font-semibold tracking-widest uppercase">{{ supplier.code }}</h3>
                    <div class="flex items-center gap-3 text-xs">
                        <a
                            v-if="supplier.open_agent_supplier_purchase_order"
                            :href="route('grp.supply-chain.agent_supplier_purchase_orders.show', [supplier.open_agent_supplier_purchase_order.slug])"
                            class="secondaryLink"
                        >
                            {{ trans("Open ASPO") }}: {{ supplier.open_agent_supplier_purchase_order.reference }}
                        </a>
                        <span class="text-gray-500 tabular-nums">{{ trans("Estimated value") }}: {{ useLocaleStore().number(supplier.estimated_value) }}</span>
                    </div>
                </div>
                <div class="my-3 border-t border-dashed border-gray-300" />

                <div v-for="product in supplier.products" :key="product.supplier_product_id" class="mt-3 border-t border-gray-100 pt-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ product.code }}</span> — {{ product.name }}
                            <span class="text-sm text-gray-500">({{ product.total_units }} u<span v-if="product.total_cartons"> / {{ product.total_cartons }} ctn</span>)</span>
                        </div>
                        <div v-if="product.minimum_carton_order" class="w-40">
                            <div class="h-2 rounded bg-gray-200">
                                <div class="h-2 rounded" :class="(product.moq_progress ?? 0) >= 100 ? 'bg-green-500' : 'bg-indigo-400'" :style="{ width: Math.min(product.moq_progress ?? 0, 100) + '%' }" />
                            </div>
                            <div class="text-xs text-gray-500">MOQ {{ product.moq_progress }}% ({{ product.minimum_carton_order }} ctn)</div>
                        </div>
                    </div>

                    <div v-for="line in product.orgs" :key="line.id" class="mt-1 flex items-center gap-3 text-sm">
                        <input v-if="editable && line.state === 'open'" type="checkbox" :checked="line.id in selected" @change="toggle(line)" />
                        <span class="rounded bg-gray-100 px-2 py-0.5">{{ line.organisation_code }}</span>
                        <input v-if="editable && line.id in selected" v-model.number="selected[line.id]" type="number" step="0.001" :max="line.units" min="0.001" class="w-24 rounded border-gray-300" />
                        <span v-else>{{ line.units }} u</span>
                        <span class="text-gray-400">{{ line.priority }}</span>
                        <span v-if="line.state === 'dismiss_proposed'" class="text-warning-600">{{ trans("dismissal proposed") }}: {{ line.dismiss_reason }}</span>
                        <span class="text-gray-400">{{ useFormatTime(line.created_at) }}</span>
                        <button v-if="editable && line.state === 'open'" type="button" class="secondaryLink ml-auto" @click="proposeDismiss(line)">
                            {{ trans("Propose dismissal") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!agents.length" class="text-gray-500">{{ trans("Nothing on the shopping list") }}</div>
    </div>
</template>
