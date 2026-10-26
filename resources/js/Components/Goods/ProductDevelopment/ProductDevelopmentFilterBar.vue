<!--
  - Author Louis Perez
  - Created on 15-10-2026-21h-10m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import qs from "qs"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import { ctrans } from "@/Composables/useTrans"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import ScrollFadeArrow from "@/Components/Utils/ScrollFadeArrow.vue"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle, faClock, faSearch, faPlayCircle, faPauseCircle, faCheckCircle, faBan, faChevronDown } from "@fal"
library.add(faExclamationTriangle, faClock, faSearch, faPlayCircle, faPauseCircle, faCheckCircle, faBan, faChevronDown)

type DropdownKey = "kind" | "stage" | "agent" | "designer" | "brand"
type FlagKey = "missing" | "overdue"

interface Chip {
    key: string
    label: string
    color: string
    icon?: string
}

const props = defineProps<{
    filterBar: {
        statuses: Chip[]
        flags: (Chip & { key: FlagKey, icon: string })[]
        options: Record<DropdownKey, Record<string, string>>
        counts: { all: number, status: Record<string, number>, missing: number, overdue: number }
    }
    routeName: string
}>()

const currentFilters = (): Record<string, string> => (qs.parse(window.location.search.substring(1)).filter as Record<string, string>) ?? {}

const selected = reactive<Record<string, string | null>>({
    status: currentFilters().status ?? null,
    kind: currentFilters().kind ?? null,
    stage: currentFilters().stage ?? null,
    agent: currentFilters().agent ?? null,
    designer: currentFilters().designer ?? null,
    brand: currentFilters().brand ?? null,
    missing: currentFilters().missing ?? null,
    overdue: currentFilters().overdue ?? null,
})

const dropdowns = computed(() => ([
    { key: "kind", label: ctrans("Kind") },
    { key: "stage", label: ctrans("Stage") },
    { key: "agent", label: ctrans("Agent") },
    { key: "designer", label: ctrans("Designer") },
    { key: "brand", label: ctrans("Brand") },
] as { key: DropdownKey, label: string }[]).filter((dropdown) => Object.keys(props.filterBar.options[dropdown.key] ?? {}).length))

const asOptions = (options: Record<string, string>) => Object.entries(options ?? {}).map(([value, label]) => ({ value, label }))

function apply() {
    const filter: Record<string, string> = { ...currentFilters() }
    Object.entries(selected).forEach(([key, value]) => {
        if (value === null || value === "") {
            delete filter[key]
        } else {
            filter[key] = value
        }
    })
    router.get(route(props.routeName), { filter }, { preserveState: true, preserveScroll: true, replace: true })
}

const emits = defineEmits<{
    (e: "toggled", isExpanded: boolean): void
}>()

const isExpanded = ref(true)

const chipScroller = ref<HTMLElement | null>(null)
const { canScrollLeft: canScrollChipsLeft, canScrollRight: canScrollChipsRight, scrollBy: scrollChipsBy } = useScrollArrows(chipScroller)

async function toggleExpanded() {
    isExpanded.value = !isExpanded.value
    await nextTick()
    emits("toggled", isExpanded.value)
}

const search = ref<string>(currentFilters().global ?? "")
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        const filter: Record<string, string> = { ...currentFilters() }
        if (value.trim()) {
            filter.global = value.trim()
        } else {
            delete filter.global
        }
        router.get(route(props.routeName), { filter }, { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

function setStatus(status: string | null) {
    selected.status = status
    apply()
}

function toggle(key: FlagKey) {
    selected[key] = selected[key] ? null : "yes"
    apply()
}

const colorOf = productDevelopmentColor

const idleChip = "border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
const idleCount = "bg-gray-100 text-gray-600"
</script>

<template>
    <div class="mx-4 mt-4 mb-4 rounded-lg border border-gray-200 bg-white md:mx-6">
        <div class="flex items-start gap-2 px-3 py-2.5" :class="isExpanded ? 'border-b border-gray-100' : ''">
            <div class="relative isolate min-w-0 flex-1">
            <div ref="chipScroller" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap [scrollbar-width:none] lg:flex-wrap lg:overflow-visible lg:whitespace-normal [&::-webkit-scrollbar]:hidden">
                <button
                    class="flex shrink-0 items-center gap-x-1.5 rounded-full border px-3 py-1 text-sm"
                    :class="!selected.status ? 'bg-[--app-accent] text-[--app-accent-text] border-[--app-accent]' : idleChip"
                    @click="setStatus(null)"
                >
                    {{ ctrans("All") }}
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="!selected.status ? 'bg-white/25' : idleCount">{{ filterBar.counts.all }}</span>
                </button>
                <button
                    v-for="status in filterBar.statuses"
                    :key="status.key"
                    class="flex shrink-0 items-center gap-x-1.5 rounded-full border px-3 py-1 text-sm"
                    :class="selected.status === status.key ? colorOf(status.color).active : idleChip"
                    @click="setStatus(status.key)"
                >
                    <FontAwesomeIcon v-if="status.icon" :icon="status.icon" :class="colorOf(status.color).icon" fixed-width aria-hidden="true" />
                    {{ status.label }}
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="selected.status === status.key ? colorOf(status.color).count : idleCount">{{ filterBar.counts.status[status.key] ?? 0 }}</span>
                </button>

                <span class="h-5 w-px shrink-0 bg-gray-200 lg:hidden" aria-hidden="true" />

                <div class="flex shrink-0 items-center gap-2 lg:ml-auto">
                    <button
                        v-for="flag in filterBar.flags"
                        :key="flag.key"
                        class="flex shrink-0 items-center gap-x-1.5 rounded-full border px-3 py-1 text-sm"
                        :class="selected[flag.key] ? colorOf(flag.color).active : idleChip"
                        @click="toggle(flag.key)"
                    >
                        <FontAwesomeIcon :icon="flag.icon" :class="colorOf(flag.color).icon" fixed-width aria-hidden="true" />
                        {{ flag.label }}
                        <span class="rounded-full px-1.5 text-xs tabular-nums" :class="selected[flag.key] ? colorOf(flag.color).count : idleCount">{{ filterBar.counts[flag.key] }}</span>
                    </button>
                </div>
            </div>
            <ScrollFadeArrow direction="left" :visible="canScrollChipsLeft" wrapperClass="lg:hidden" @click="scrollChipsBy(-1)" />
            <ScrollFadeArrow direction="right" :visible="canScrollChipsRight" wrapperClass="lg:hidden" @click="scrollChipsBy(1)" />
            </div>
            <button
                    type="button"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-50 hover:text-gray-600"
                    :aria-expanded="isExpanded"
                    :aria-label="isExpanded ? ctrans('Hide filters') : ctrans('Show filters')"
                    @click="toggleExpanded"
                >
                    <FontAwesomeIcon icon="fal fa-chevron-down" class="transition-transform" :class="isExpanded ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                </button>
        </div>

        <div v-show="isExpanded" class="grid grid-cols-2 gap-2 px-3 py-2.5 sm:flex sm:flex-wrap sm:items-center">
            <IconField class="col-span-2 w-full sm:w-64">
                <InputIcon>
                    <FontAwesomeIcon icon="fal fa-search" class="text-gray-400" fixed-width aria-hidden="true" />
                </InputIcon>
                <InputText v-model="search" size="small" class="w-full" :placeholder="ctrans('Search code, name or family')" />
            </IconField>

            <Select
                v-for="dropdown in dropdowns"
                :key="dropdown.key"
                v-model="selected[dropdown.key]"
                :options="asOptions(filterBar.options[dropdown.key])"
                optionLabel="label"
                optionValue="value"
                :placeholder="dropdown.label"
                showClear
                filter
                size="small"
                class="filter-select w-full sm:w-auto"
                :class="{ 'filter-select-active': selected[dropdown.key] }"
                @change="apply"
            />
        </div>
    </div>
</template>

<style scoped>
@media (min-width: 640px) {
    .filter-select {
        min-width: 9rem;
    }
}
.filter-select :deep(.p-select-label) {
    font-size: 0.875rem;
}
.filter-select-active {
    border-color: var(--app-accent);
    background-color: var(--app-accent-soft);
}
</style>
