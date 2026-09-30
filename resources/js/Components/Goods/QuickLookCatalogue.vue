<!--
  - Author Louis Perez
  - Created on 29-09-2026-13h-23m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faEye, faChartLine, faHistory } from "@fal"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SalesAnalysis from "@/Components/SalesAnalysis/SalesAnalysis.vue"
import QuickLookHistory from "@/Components/Goods/QuickLookHistory.vue"
import { ctrans } from "@/Composables/useTrans"
import type { QuickLookHistoryEntry, QuickLookHistoryRecordResolver, QuickLookOverview, QuickLookRoutes, QuickLookTab } from "@/types/QuickLookCatalogue"
library.add(faExternalLink, faEye, faChartLine, faHistory)

const props = defineProps<{
    isOpen: boolean
    kind: string
    code?: string | null
    routes: QuickLookRoutes | null
    pageUrl: string | null
    initialTab?: QuickLookTab
    historyNotice?: string | null
    historyRecordResolver?: QuickLookHistoryRecordResolver
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

defineSlots<{
    overview(props: { data: QuickLookOverview; openSalesAnalysis: () => void }): any
}>()

const tabs: { key: QuickLookTab; label: string; icon: string }[] = [
    { key: "overview", label: ctrans("Overview"), icon: "eye" },
    { key: "sales_analysis", label: ctrans("Sales analysis"), icon: "chart-line" },
    { key: "history", label: ctrans("History"), icon: "history" },
]

const activeTab = ref<QuickLookTab>("overview")
const overview = ref<QuickLookOverview | null>(null)
const salesAnalysis = ref<any>(null)
const history = ref<QuickLookHistoryEntry[] | null>(null)
const loading = reactive<Record<QuickLookTab, boolean>>({ overview: false, sales_analysis: false, history: false })
const errors = reactive<Record<QuickLookTab, string | null>>({ overview: null, sales_analysis: null, history: null })

let generation = 0

async function fetchTab(tab: QuickLookTab, params: Record<string, unknown> = {}): Promise<void> {
    const url = props.routes?.[tab]
    if (!url) return

    const requestGeneration = generation
    loading[tab] = true
    errors[tab] = null
    try {
        const { data } = await axios.get(url, { params })
        if (requestGeneration !== generation) return
        if (tab === "overview") overview.value = data
        if (tab === "sales_analysis") salesAnalysis.value = data
        if (tab === "history") history.value = data.data
    } catch {
        if (requestGeneration === generation) errors[tab] = ctrans("Could not load this, please try again")
    } finally {
        if (requestGeneration === generation) loading[tab] = false
    }
}

const reloadSalesAnalysis = (params: Record<string, string | number | undefined>) => fetchTab("sales_analysis", params)

const openSalesAnalysis = (): void => {
    activeTab.value = "sales_analysis"
}

watch(
    () => [props.isOpen, props.routes?.overview],
    ([isOpen]) => {
        if (!isOpen) return
        generation++
        activeTab.value = props.initialTab ?? "overview"
        overview.value = null
        salesAnalysis.value = null
        history.value = null
        for (const tab of tabs) {
            loading[tab.key] = false
            errors[tab.key] = null
        }
        fetchTab("overview")
        if (activeTab.value !== "overview") fetchTab(activeTab.value)
    },
    { immediate: true }
)

watch(activeTab, (tab) => {
    if (tab === "sales_analysis" && !salesAnalysis.value && !loading.sales_analysis) fetchTab(tab)
    if (tab === "history" && !history.value && !loading.history) fetchTab(tab)
})

const isTabLoading = computed(() => activeTab.value !== "sales_analysis" && loading[activeTab.value])

const fullHistoryUrl = computed(() => (props.pageUrl ? `${props.pageUrl}?tab=history` : null))
</script>

<template>
    <Modal :isOpen="isOpen" width="w-full max-w-6xl" closeButton @onClose="emits('onClose')">
        <div class="-mx-6 -mt-6 flex flex-wrap items-center justify-between gap-3 rounded-t-2xl border-b border-gray-200 bg-gray-50 px-6 pt-4">
            <div class="min-w-0">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ kind }}</div>
                <div class="truncate text-lg font-semibold text-gray-900">
                    {{ overview?.code ?? code }}<span v-if="overview?.name" class="font-normal text-gray-500"> — {{ overview.name }}</span>
                </div>
            </div>
            <a
                v-if="pageUrl"
                v-tooltip="ctrans('Open in new tab')"
                :href="pageUrl"
                target="_blank"
                rel="noopener"
                :aria-label="ctrans('Open in new tab')"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-[--app-accent] text-[--app-accent-text] shadow-sm transition-colors hover:bg-[--app-accent-strong]"
            >
                <FontAwesomeIcon :icon="['fal', 'external-link']" fixed-width aria-hidden="true" />
            </a>
            <nav class="-mb-px flex w-full gap-1" role="tablist">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    class="flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                    :class="[
                        tab.key === 'history' ? 'ml-auto' : '',
                        activeTab === tab.key ? 'border-[--app-accent] text-[--app-accent]' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-800',
                    ]"
                    @click="activeTab = tab.key"
                >
                    <FontAwesomeIcon :icon="['fal', tab.icon]" fixed-width aria-hidden="true" />
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <div v-if="isTabLoading" class="flex items-center justify-center gap-2 py-20 text-sm text-gray-500" role="status">
            <LoadingIcon class="text-[--app-accent]" />
            {{ ctrans("We're still fetching the data. Please wait") }}
        </div>
        <div v-else-if="errors[activeTab]" class="py-20 text-center text-sm text-red-600">{{ errors[activeTab] }}</div>
        <div v-else class="-mx-6 -mb-6 max-h-[75vh] overflow-y-auto">
            <slot v-if="activeTab === 'overview' && overview" name="overview" :data="overview" :openSalesAnalysis="openSalesAnalysis" />
            <SalesAnalysis v-else-if="activeTab === 'sales_analysis'" :data="salesAnalysis ?? undefined" :reloadWith="reloadSalesAnalysis" compact />
            <QuickLookHistory v-else-if="activeTab === 'history' && history" :entries="history" :fullHistoryUrl="fullHistoryUrl" :notice="historyNotice" :recordResolver="historyRecordResolver" />
        </div>
    </Modal>
</template>
