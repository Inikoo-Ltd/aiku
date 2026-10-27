<!--
  - Author Louis Perez
  - Created on 26-10-2026-21h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Select from "primevue/select"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { skuProgress, skuProgressPills, type SkuListEntry } from "@/Components/Goods/ProductDevelopment/productDevelopmentSkuProgress"

type Filter = "open" | "complete" | "all"

const props = defineProps<{
    entries: SkuListEntry[]
}>()

const selectedId = defineModel<number | null>({ required: true })

const filter = ref<Filter>("all")
const filters = computed(() => [
    { value: "open" as Filter, label: ctrans("To do"), count: props.entries.filter((entry) => skuProgress(entry) !== "complete").length },
    { value: "complete" as Filter, label: ctrans("Done"), count: props.entries.filter((entry) => skuProgress(entry) === "complete").length },
    { value: "all" as Filter, label: ctrans("All"), count: props.entries.length },
])

const visible = computed(() => props.entries.filter((entry) => filter.value === "all" || (filter.value === "complete") === (skuProgress(entry) === "complete")))

const selected = computed(() => props.entries.find((entry) => entry.id === selectedId.value) ?? null)

watch(filter, () => {
    if (visible.value.length && !visible.value.some((entry) => entry.id === selectedId.value)) {
        selectedId.value = visible.value[0].id
    }
})
</script>

<template>
    <div>
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-3 py-2 2xl:hidden">
        <SegmentedToggle v-model="filter" :options="filters" :ariaLabel="ctrans('Show SKUs')" soft class="sku-filter-compact" />
        <Select v-model="selectedId" :options="visible" optionValue="id" optionLabel="code" filter :filterFields="['code', 'name']" class="min-w-[16rem] flex-1" overlayClass="text-sm" :aria-label="ctrans('SKU')">
            <template #value>
                <span v-if="selected" class="flex min-w-0 items-center gap-2">
                    <span class="whitespace-nowrap font-medium text-gray-900">{{ selected.code }}</span>
                    <span class="truncate text-gray-500">{{ selected.name }}</span>
                </span>
            </template>
            <template #option="{ option }">
                <span class="flex w-full items-center gap-2">
                    <span class="whitespace-nowrap font-medium text-gray-900">{{ option.code }}</span>
                    <span class="min-w-0 flex-1 truncate text-gray-500">{{ option.name }}</span>
                    <span v-if="option.badge" class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium" :class="option.badge.class">{{ option.badge.label }}</span>
                    <span v-else class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium" :class="skuProgressPills[skuProgress(option)].class">
                        <span class="h-1.5 w-1.5 rounded-full" :class="skuProgressPills[skuProgress(option)].dot" aria-hidden="true" />
                        {{ ctrans(skuProgressPills[skuProgress(option)].label) }}
                    </span>
                </span>
            </template>
        </Select>
    </div>
    <nav class="hidden h-full flex-col border-r border-gray-100 2xl:flex" :aria-label="ctrans('SKUs')">
        <div class="border-b border-gray-100 px-2 py-2">
            <SegmentedToggle v-model="filter" :options="filters" :ariaLabel="ctrans('Show SKUs')" soft class="sku-filter" />
        </div>
        <ul class="flex-1 divide-y divide-gray-100 overflow-y-auto [scrollbar-width:thin]">
            <li v-for="entry in visible" :key="entry.id">
                <button
                    type="button"
                    class="w-full border-l-2 px-3 py-2.5 text-left"
                    :class="selectedId === entry.id ? 'border-[--app-accent] bg-[--app-accent-soft]' : 'border-transparent hover:bg-gray-50'"
                    :aria-current="selectedId === entry.id ? 'true' : undefined"
                    @click="selectedId = entry.id"
                >
                    <span class="flex items-start gap-2.5">
                    <span v-if="entry.image !== undefined" class="h-9 w-9 shrink-0 overflow-hidden rounded-md bg-gray-100">
                        <Image v-if="entry.image" :src="entry.image" :alt="entry.code" class="h-full w-full object-cover" />
                    </span>
                    <span class="min-w-0 flex-1">
                    <span class="block whitespace-nowrap text-sm font-medium text-gray-900">{{ entry.code }}</span>
                    <span class="block truncate text-xs text-gray-500" :title="entry.name">{{ entry.name }}</span>
                    <span v-if="entry.badge" class="mt-1.5 inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium" :class="entry.badge.class">{{ entry.badge.label }}</span>
                    <span v-else class="mt-1.5 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium" :class="skuProgressPills[skuProgress(entry)].class">
                        <span class="h-1.5 w-1.5 rounded-full" :class="skuProgressPills[skuProgress(entry)].dot" aria-hidden="true" />
                        {{ ctrans(skuProgressPills[skuProgress(entry)].label) }}
                        <span v-if="skuProgress(entry) === 'in_progress'" class="tabular-nums opacity-70">{{ entry.done }}/{{ entry.total }}</span>
                    </span>
                    </span>
                    </span>
                </button>
            </li>
            <li v-if="!visible.length" class="px-3 py-6 text-center text-xs text-gray-400">
                {{ filter === "complete" ? ctrans("No SKU complete yet") : ctrans("Nothing left to do") }}
            </li>
        </ul>
    </nav>
    </div>
</template>

<style scoped>
.sku-filter {
    display: flex;
    width: 100%;
}
.sku-filter-compact :deep(.p-togglebutton) {
    font-size: 0.75rem;
}
.sku-filter-compact :deep(.p-togglebutton-content) {
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
}
.sku-filter :deep(.p-togglebutton) {
    flex: 1 1 0;
    min-width: 0;
    font-size: 0.75rem;
}
.sku-filter :deep(.p-togglebutton-content) {
    justify-content: center;
    padding: 0.25rem 0.375rem;
    white-space: nowrap;
}
.sku-filter :deep(.segmented-toggle-count) {
    min-width: 1rem;
    padding: 0 0.25rem;
    font-size: 0.6875rem;
}
</style>
