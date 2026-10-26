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
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ProductDevelopmentLabelPackaging from "@/Components/Goods/ProductDevelopment/ProductDevelopmentLabelPackaging.vue"
import ProductDevelopmentProgressBar from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProgressBar.vue"
import ProductDevelopmentSpecField from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSpecField.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import type { ChecklistEditorData, ChecklistRow, ChecklistSku } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faClipboardList, faTags } from "@fal"
library.add(faExternalLink, faClipboardList, faTags)

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

const savingOnLabel = ref<number | null>(null)

function setOnLabel(row: ChecklistRow, include: boolean) {
    if (!props.data.routes.on_label) {
        return
    }
    router.patch(route(props.data.routes.on_label, { productDevelopmentChecklistItem: row.id }), { include }, {
        preserveScroll: true,
        only: ["compliance"],
        onStart: () => savingOnLabel.value = row.id,
        onFinish: () => savingOnLabel.value = null,
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
                <SegmentedToggle v-model="view" :options="views" :ariaLabel="ctrans('Show')" soft class="ml-auto" />
            </header>

            <div class="grid md:grid-cols-[17rem_minmax(0,1fr)]">
                <nav class="max-h-[28rem] overflow-y-auto border-b border-gray-100 [scrollbar-width:thin] md:max-h-none md:border-b-0 md:border-r" :aria-label="ctrans('SKUs')">
                    <ul class="divide-y divide-gray-100">
                        <li v-for="sku in skus" :key="sku.id">
                            <button
                                type="button"
                                class="w-full border-l-2 px-3 py-2.5 text-left"
                                :class="selected?.id === sku.id ? 'border-[--app-accent] bg-[--app-accent-soft]' : 'border-transparent hover:bg-gray-50'"
                                :aria-current="selected?.id === sku.id ? 'true' : undefined"
                                @click="selectedId = sku.id"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="whitespace-nowrap text-sm font-medium text-gray-900">{{ sku.code }}</span>
                                    <span v-if="summary(sku).missing.length" class="shrink-0 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-800">{{ ctrans(":count missing", { count: String(summary(sku).missing.length) }) }}</span>
                                    <span v-else class="shrink-0 rounded bg-green-50 px-1.5 py-0.5 text-xs text-green-700">{{ ctrans("Complete") }}</span>
                                </div>
                                <div class="truncate text-xs text-gray-500" :title="sku.name">{{ sku.name }}</div>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <ProductDevelopmentProgressBar :done="summary(sku).done" :total="summary(sku).total" />
                                    <span class="text-xs tabular-nums text-gray-500">{{ summary(sku).done }}/{{ summary(sku).total }}</span>
                                </div>
                            </button>
                        </li>
                    </ul>
                </nav>

                <div v-if="selected" :key="selected.id" class="min-w-0 p-4">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="whitespace-nowrap text-base font-semibold text-gray-900">{{ selected.code }}</span>
                                <Link :href="route(selected.route.name, selected.route.parameters)" class="text-xs text-gray-400 hover:text-[--app-accent]" v-tooltip="ctrans('Open the trade unit')" :aria-label="ctrans('Open the trade unit')">
                                    <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                                </Link>
                            </div>
                            <div class="text-sm text-gray-600">{{ selected.name }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-28"><ProductDevelopmentProgressBar :done="summary(selected).done" :total="summary(selected).total" /></div>
                            <span class="text-xs tabular-nums text-gray-500">{{ summary(selected).done }}/{{ summary(selected).total }}</span>
                        </div>
                    </div>
                    <template v-if="view === 'information'">
                        <p v-if="summary(selected).missing.length" class="mb-4 text-xs text-amber-800">
                            <span class="font-medium">{{ ctrans("Still missing:") }}</span> {{ summary(selected).missing.join(", ") }}
                        </p>
                        <p class="mb-4 text-xs text-gray-500">{{ ctrans("Saved values go straight to the trade unit. Tick \"Include on label\" for the text that must be printed on the packaging.") }}</p>
                        <div class="grid gap-x-4 gap-y-4 sm:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="row in rowsOf(selected)"
                                :key="row.id"
                                :class="['textarea', 'pictograms', 'label_marks', 'ingredients', 'photos'].includes(row.input_type) ? 'sm:col-span-2 xl:col-span-3' : ''"
                            >
                                <ProductDevelopmentSpecField :row="row" :updateRoute="data.routes.update" :countries="data.countries" :options="data.options" />
                                <label v-if="row.label_field" class="mt-1.5 inline-flex cursor-pointer items-center gap-2 text-xs text-gray-700">
                                    <Checkbox
                                        :modelValue="!!row.on_label"
                                        binary
                                        :inputId="`on-label-${row.id}`"
                                        :disabled="!row.can_edit || savingOnLabel === row.id"
                                        @update:modelValue="(include: boolean) => setOnLabel(row, include)"
                                    />
                                    {{ ctrans("Include on label") }}
                                </label>
                            </div>
                        </div>
                    </template>
                    <ProductDevelopmentLabelPackaging v-else-if="selected.label_content_route" :contentRoute="selected.label_content_route" />
                </div>
            </div>
        </section>

        <p v-else class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No SKUs in this development yet") }}</p>
    </div>
</template>
