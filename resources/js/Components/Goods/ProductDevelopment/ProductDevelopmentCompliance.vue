<!--
  - Author Louis Perez
  - Created on 15-10-2026-19h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import Select from "primevue/select"
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ProductDevelopmentLabelPackaging from "@/Components/Goods/ProductDevelopment/ProductDevelopmentLabelPackaging.vue"
import RecipeIngredients from "@/Components/Goods/ProductDevelopment/Formulation/RecipeIngredients.vue"
import ProductDevelopmentSkuList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSkuList.vue"
import { skuProgress, skuProgressPills } from "@/Components/Goods/ProductDevelopment/productDevelopmentSkuProgress"
import ProductDevelopmentProgressBar from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProgressBar.vue"
import ProductDevelopmentSpecField from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSpecField.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import type { ChecklistEditorData, ChecklistSku } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faClipboardList, faTags, faInfoCircle } from "@fal"
import { faAsterisk } from "@fas"
library.add(faExternalLink, faClipboardList, faTags, faInfoCircle, faAsterisk)

const props = defineProps<{
    data: ChecklistEditorData
}>()

const role = computed(() => props.data?.skus.flatMap((sku) => sku.rows)[0]?.role ?? null)

const rowsOf = (sku: ChecklistSku) => sku.rows.filter((row) => row.role.value === role.value?.value)

const summary = (sku: ChecklistSku) => {
    const required = rowsOf(sku).filter((row) => row.required)
    const missing = required.filter((row) => row.state === "missing")

    return { done: required.length - missing.length, total: required.length, missing: missing.map((row) => row.label) }
}

const skus = computed(() => (props.data?.skus ?? []).filter((sku) => rowsOf(sku).length))

const selectedId = ref<number | null>((skus.value.find((sku) => summary(sku).missing.length) ?? skus.value[0])?.id ?? null)
const selected = computed(() => skus.value.find((sku) => sku.id === selectedId.value) ?? skus.value[0] ?? null)

const color = computed(() => productDevelopmentColor(role.value?.color ?? "gray"))

type View = "information" | "label"
const view = ref<View>("information")
const views = computed(() => [
    { value: "information" as View, label: ctrans("Compliance information"), icon: "fal fa-clipboard-list" },
    { value: "label" as View, label: ctrans("Label & packaging"), icon: "fal fa-tags" },
])

const entries = computed(() => skus.value.map((sku) => ({ id: sku.id, code: sku.code, name: sku.name, ...summary(sku) })))
const progressOf = (sku: ChecklistSku) => skuProgress(summary(sku))
const progressPills = skuProgressPills

const savingOnLabel = ref<string | null>(null)

function setOnLabel(sku: ChecklistSku, field: string, include: boolean) {
    if (!props.data.routes.on_label) {
        return
    }
    router.patch(route(props.data.routes.on_label, { productDevelopmentItem: sku.id }), { field, include }, {
        preserveScroll: true,
        only: ["compliance"],
        onStart: () => savingOnLabel.value = field,
        onFinish: () => savingOnLabel.value = null,
    })
}

const savingTemplate = ref(false)

function setTemplate(category: string) {
    const template = props.data.template
    if (!template?.route) {
        return
    }
    router.patch(route(template.route.name, template.route.parameters), { checklist_category: category }, {
        preserveScroll: true,
        only: ["compliance", "overview"],
        onStart: () => savingTemplate.value = true,
        onFinish: () => savingTemplate.value = false,
    })
}
</script>

<template>
    <div v-if="data" class="space-y-3">
        <section v-if="skus.length" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-gray-100 px-4 py-3">
                <FontAwesomeIcon v-if="role" :icon="role.icon" :class="color.icon" fixed-width aria-hidden="true" />
                <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Compliance details") }}</h3>
                <span class="text-xs text-gray-500">{{ ctrans(":done of :total SKUs done", { done: String(skus.filter((sku) => !summary(sku).missing.length).length), total: String(skus.length) }) }}</span>
                <div v-if="data.template && data.template.options.length > 1" class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">{{ ctrans("Compliance template") }}</span>
                    <Select :modelValue="data.template.value" :options="data.template.options" optionLabel="label" optionValue="value" size="small" class="min-w-[12rem]" overlayClass="text-sm" :disabled="!data.template.can_change || savingTemplate" @update:modelValue="setTemplate" />
                </div>
                <SegmentedToggle v-model="view" :options="views" :ariaLabel="ctrans('Show')" soft class="ml-auto" />
            </header>

            <div class="grid 2xl:grid-cols-[18rem_minmax(0,1fr)]">
                <ProductDevelopmentSkuList v-model="selectedId" :entries="entries" />

                <div v-if="selected" :key="selected.id" class="min-w-0 p-4">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                        <div class="flex min-w-0 flex-wrap items-baseline gap-x-3">
                            <span class="text-xs text-gray-500">{{ ctrans("SKU") }}</span>
                            <span class="whitespace-nowrap text-xl font-semibold text-gray-900">{{ selected.code }}</span>
                            <span class="text-base text-gray-700">{{ selected.name }}</span>
                            <Link :href="route(selected.route.name, selected.route.parameters)" class="text-xs text-gray-400 hover:text-[--app-accent]" v-tooltip="ctrans('Open the trade unit')" :aria-label="ctrans('Open the trade unit')">
                                <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                            </Link>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" :class="progressPills[progressOf(selected)].class">
                                <span class="h-1.5 w-1.5 rounded-full" :class="progressPills[progressOf(selected)].dot" aria-hidden="true" />
                                {{ ctrans(progressPills[progressOf(selected)].label) }}
                            </span>
                            <div class="w-24"><ProductDevelopmentProgressBar :done="summary(selected).done" :total="summary(selected).total" /></div>
                            <span class="text-xs tabular-nums text-gray-500">{{ summary(selected).done }}/{{ summary(selected).total }}</span>
                        </div>
                    </div>
                    <template v-if="view === 'information'">
                        <p v-if="summary(selected).missing.length" class="mb-4 text-xs text-amber-800">
                            <span class="font-medium">{{ ctrans("Still missing:") }}</span> {{ summary(selected).missing.join(", ") }}
                        </p>
                        <p class="mb-3 text-xs text-gray-500">{{ ctrans("Saved values go straight to the trade unit. Tick \"On label\" for what must be printed on the packaging.") }}</p>
                        <div class="overflow-x-auto rounded-md border border-gray-200">
                            <table class="w-full min-w-[40rem] text-sm">
                                <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                                    <tr>
                                        <th class="w-48 px-3 py-2">{{ ctrans("Information required") }}</th>
                                        <th class="px-3 py-2">{{ ctrans("Details") }}</th>
                                        <th class="w-20 px-3 py-2 text-center">{{ ctrans("On label") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in rowsOf(selected)" :key="row.id" class="align-top">
                                        <th scope="row" class="px-3 py-3 text-left font-medium text-gray-800">
                                            <span class="[&>svg]:ml-1 [&>svg]:align-baseline">
                                                {{ row.label }}
                                                <FontAwesomeIcon v-if="row.required" icon="fas fa-asterisk" class="text-[9px] text-red-500" :aria-label="ctrans('Required')" />
                                                <FontAwesomeIcon v-if="row.information" v-tooltip="row.information" icon="fal fa-info-circle" class="cursor-help text-xs text-gray-400" fixed-width :aria-label="row.information" />
                                            </span>
                                            <span v-if="!row.required" class="block text-xs font-normal text-gray-400">{{ ctrans("optional") }}</span>
                                        </th>
                                        <td class="px-3 py-2">
                                            <ProductDevelopmentSpecField :row="row" :updateRoute="data.routes.update" :countries="data.countries" :options="data.options" hideLabel />
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <Checkbox
                                                v-if="row.label_field"
                                                :modelValue="!!row.on_label"
                                                binary
                                                :inputId="`on-label-${row.id}`"
                                                :disabled="!row.can_edit || savingOnLabel === row.label_field"
                                                :aria-label="ctrans('Put :field on the label', { field: row.label })"
                                                @update:modelValue="(include: boolean) => setOnLabel(selected!, row.label_field!, include)"
                                            />
                                            <span v-else class="text-xs text-gray-300" aria-hidden="true">—</span>
                                        </td>
                                    </tr>
                                    <template v-if="selected.pack_info?.length">
                                        <tr class="bg-gray-50">
                                            <td colspan="3" class="px-3 py-1.5 text-xs font-medium text-gray-500">{{ ctrans("Pack information (filled in on the Products tab)") }}</td>
                                        </tr>
                                        <tr v-for="info in selected.pack_info" :key="info.key">
                                            <th scope="row" class="px-3 py-2.5 text-left font-medium text-gray-800">{{ info.label }}</th>
                                            <td class="px-3 py-2.5" :class="info.value ? 'text-gray-800' : 'text-gray-400'">{{ info.value || ctrans("Not filled in yet") }}</td>
                                            <td class="px-3 py-2.5 text-center">
                                                <Checkbox
                                                    :modelValue="info.on_label"
                                                    binary
                                                    :inputId="`on-label-${info.key}`"
                                                    :disabled="savingOnLabel === info.key"
                                                    :aria-label="ctrans('Put :field on the label', { field: info.label })"
                                                    @update:modelValue="(include: boolean) => setOnLabel(selected!, info.key, include)"
                                                />
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <section v-if="data.in_house" class="mt-6 border-t border-gray-100 pt-4">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Approved formulation") }}</h4>
                            <template v-if="selected.formulation">
                                <RecipeIngredients :ingredients="selected.formulation.ingredients" />
                                <p class="mt-1 text-xs text-gray-500">{{ ctrans("Sample :number, the final recipe for the compliance assessment", { number: selected.formulation.number }) }}</p>
                            </template>
                            <p v-else class="text-xs text-gray-400">{{ ctrans("No sample approved yet") }}</p>
                        </section>

                        <section v-if="selected.components?.length" class="mt-6 border-t border-gray-100 pt-4">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Packaging & components") }}</h4>
                            <ul class="space-y-2">
                                <li v-for="component in selected.components" :key="component.id" class="rounded-md border px-3 py-2.5 text-sm" :class="component.missing.length ? 'border-amber-200 bg-amber-50/40' : 'border-gray-200'">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <Link :href="route(component.route.name, component.route.parameters)" class="whitespace-nowrap font-medium text-gray-900 hover:text-[--app-accent]">{{ component.code }}</Link>
                                        <span class="text-gray-600">{{ component.name }}</span>
                                        <span class="text-xs" :class="component.approved ? 'text-green-700' : 'text-amber-700'">{{ component.status }}</span>
                                        <span v-if="component.missing.length" class="ml-auto text-xs text-amber-800">{{ ctrans("Missing: :items", { items: component.missing.join(", ") }) }}</span>
                                    </div>
                                    <dl class="mt-1.5 grid gap-x-6 gap-y-1 text-xs sm:grid-cols-3">
                                        <div>
                                            <dt class="text-gray-500">{{ ctrans("Materials") }}</dt>
                                            <dd class="text-gray-800">{{ component.materials.join(", ") || "—" }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">{{ ctrans("Material codes") }}</dt>
                                            <dd class="text-gray-800">{{ component.material_codes.join(", ") || "—" }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">{{ ctrans("Documents") }}</dt>
                                            <dd class="flex flex-wrap gap-x-2">
                                                <a v-for="document in component.documents" :key="document.url" :href="document.url" class="text-[--app-accent] hover:underline" :title="document.name">{{ document.label }}</a>
                                                <span v-if="!component.documents.length" class="text-gray-800">—</span>
                                            </dd>
                                        </div>
                                    </dl>
                                </li>
                            </ul>
                        </section>
                    </template>
                    <ProductDevelopmentLabelPackaging v-else-if="selected.label_content_route" :contentRoute="selected.label_content_route" />
                </div>
            </div>
        </section>

        <p v-else class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No SKUs in this development yet") }}</p>
    </div>
</template>
