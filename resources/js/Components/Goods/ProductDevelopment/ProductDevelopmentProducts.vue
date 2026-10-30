<!--
  - Author Louis Perez
  - Created on 16-10-2026-00h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import ProductDevelopmentProgressBar from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProgressBar.vue"
import ProductDevelopmentSkuList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSkuList.vue"
import ProductDevelopmentSpecField from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSpecField.vue"
import ProductDevelopmentSupplierProductLink from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSupplierProductLink.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { skuProgress, skuProgressPills } from "@/Components/Goods/ProductDevelopment/productDevelopmentSkuProgress"
import type { ChecklistEditorData, ChecklistRow, ChecklistSku } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faInfoCircle, faLink, faAtom } from "@fal"
import { faAsterisk } from "@fas"
library.add(faExternalLink, faInfoCircle, faLink, faAtom, faAsterisk)

const props = defineProps<{
    data: {
        rows: { id: number, supplier_product: string | null, label_status: string }[]
        in_house?: boolean
        editor: ChecklistEditorData
    }
}>()

const roleTitles: Record<string, string> = {
    agent: "Product specs from agent",
    purchasing: "Commercial terms from purchasing",
    production: "Product specs from production",
}

const rowsOf = (sku: ChecklistSku) => sku.rows.filter((row) => row.role.value !== "compliance")

const summary = (sku: ChecklistSku) => {
    const required = rowsOf(sku).filter((row) => row.required)
    const missing = required.filter((row) => row.state === "missing")

    return { done: required.length - missing.length, total: required.length, missing: missing.map((row) => row.label) }
}

const skus = computed(() => (props.data?.editor?.skus ?? []).filter((sku) => rowsOf(sku).length))
const entries = computed(() => skus.value.map((sku) => ({ id: sku.id, code: sku.code, name: sku.name, ...summary(sku) })))

const selectedId = ref<number | null>((skus.value.find((sku) => summary(sku).missing.length) ?? skus.value[0])?.id ?? null)
const selected = computed(() => skus.value.find((sku) => sku.id === selectedId.value) ?? skus.value[0] ?? null)
const overview = computed(() => props.data?.rows.find((row) => row.id === selected.value?.id) ?? null)

const sectionsOf = (sku: ChecklistSku) => {
    const sections = new Map<string, { role: ChecklistRow["role"], rows: ChecklistRow[] }>()
    for (const row of rowsOf(sku)) {
        if (!sections.has(row.role.value)) {
            sections.set(row.role.value, { role: row.role, rows: [] })
        }
        sections.get(row.role.value)!.rows.push(row)
    }

    return [...sections.values()]
}

const needsSupplierProduct = (sku: ChecklistSku) => rowsOf(sku).some((row) => row.needs_supplier_product)

const linkDialog = ref<InstanceType<typeof ProductDevelopmentSupplierProductLink> | null>(null)
</script>

<template>
    <div v-if="data" class="space-y-3">
        <section v-if="skus.length" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-gray-100 px-4 py-3">
                <FontAwesomeIcon icon="fal fa-atom" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                <h3 class="text-sm font-semibold text-gray-900">{{ data.in_house ? ctrans("Product specs") : ctrans("Product specs and terms") }}</h3>
                <span class="text-xs text-gray-500">{{ ctrans(":done of :total SKUs done", { done: String(entries.filter((entry) => !entry.missing.length).length), total: String(entries.length) }) }}</span>
                <span class="ml-auto text-xs text-gray-500">{{ data.in_house ? ctrans("Saved values go straight to the trade unit") : ctrans("Saved values go straight to the trade unit and supplier product") }}</span>
            </header>

            <div class="grid 2xl:grid-cols-[18rem_minmax(0,1fr)]">
                <ProductDevelopmentSkuList v-model="selectedId" :entries="entries" />

                <div v-if="selected" :key="selected.id" class="min-w-0 space-y-5 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-baseline gap-x-3">
                                <span class="text-xs text-gray-500">{{ ctrans("SKU") }}</span>
                                <span class="whitespace-nowrap text-xl font-semibold text-gray-900">{{ selected.code }}</span>
                                <span class="text-base text-gray-700">{{ selected.name }}</span>
                                <Link :href="route(selected.route.name, selected.route.parameters)" class="text-xs text-gray-400 hover:text-[--app-accent]" v-tooltip="ctrans('Open the trade unit')" :aria-label="ctrans('Open the trade unit')">
                                    <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                                </Link>
                            </div>
                            <div class="mt-0.5 flex flex-wrap gap-x-4 text-xs text-gray-500">
                                <span v-if="!data.in_house">{{ ctrans("Supplier product") }}: <span class="text-gray-800">{{ selected.supplier_product?.code ?? overview?.supplier_product ?? ctrans("not linked") }}</span></span>
                                <span v-if="overview?.label_status">{{ ctrans("Label") }}: <span class="text-gray-800">{{ overview.label_status }}</span></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="skuProgressPills[skuProgress(summary(selected))].class">
                                <span class="h-1.5 w-1.5 rounded-full" :class="skuProgressPills[skuProgress(summary(selected))].dot" aria-hidden="true" />
                                {{ ctrans(skuProgressPills[skuProgress(summary(selected))].label) }}
                            </span>
                            <div class="w-24"><ProductDevelopmentProgressBar :done="summary(selected).done" :total="summary(selected).total" /></div>
                            <span class="text-xs tabular-nums text-gray-500">{{ summary(selected).done }}/{{ summary(selected).total }}</span>
                        </div>
                    </div>

                    <p v-if="summary(selected).missing.length" class="text-xs text-amber-800">
                        <span class="font-medium">{{ ctrans("Still missing:") }}</span> {{ summary(selected).missing.join(", ") }}
                    </p>

                    <div v-if="needsSupplierProduct(selected)" class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-amber-50/70 px-3 py-2 text-xs text-amber-800">
                        <span>{{ ctrans("Carton, pack and lead time are saved on the supplier product: link one to :sku first", { sku: selected.code }) }}</span>
                        <Button v-if="selected.can_link_supplier_product" type="tertiary" size="xs" :label="ctrans('Link supplier product')" @click="linkDialog?.open(selected)">
                            <template #icon><FontAwesomeIcon icon="fal fa-link" class="text-[--app-accent]" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>

                    <section v-for="section in sectionsOf(selected)" :key="section.role.value">
                        <h4 class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <FontAwesomeIcon :icon="section.role.icon" :class="productDevelopmentColor(section.role.color).icon" fixed-width aria-hidden="true" />
                            {{ ctrans(roleTitles[section.role.value] ?? section.role.label) }}
                        </h4>
                        <div class="overflow-x-auto rounded-md border border-gray-200">
                            <table class="w-full min-w-[36rem] text-sm">
                                <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                                    <tr>
                                        <th class="w-48 px-3 py-2">{{ ctrans("Information required") }}</th>
                                        <th class="px-3 py-2">{{ ctrans("Details") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in section.rows" :key="row.id" class="align-top">
                                        <th scope="row" class="px-3 py-3 text-left font-medium text-gray-800">
                                            <span class="[&>svg]:ml-1 [&>svg]:align-baseline">
                                                {{ row.label }}<template v-if="row.input_type === 'dimensions'"> W × L × H</template>
                                                <FontAwesomeIcon v-if="row.required" icon="fas fa-asterisk" class="text-[9px] text-red-500" :aria-label="ctrans('Required')" />
                                                <FontAwesomeIcon v-if="row.information" v-tooltip="row.information" icon="fal fa-info-circle" class="cursor-help text-xs text-gray-400" fixed-width :aria-label="row.information" />
                                            </span>
                                            <span v-if="!row.required" class="block text-xs font-normal text-gray-400">{{ ctrans("optional") }}</span>
                                        </th>
                                        <td class="px-3 py-2">
                                            <ProductDevelopmentSpecField :row="row" :updateRoute="data.editor.routes.update" :countries="data.editor.countries" :options="data.editor.options" hideLabel />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </section>

        <p v-else class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No SKUs in this development yet") }}</p>

        <ProductDevelopmentSupplierProductLink ref="linkDialog" :linkRoute="data.editor.routes.supplier_product" :searchRoute="data.editor.routes.supplier_products_search" :currency="data.editor.currency" />
    </div>
</template>
