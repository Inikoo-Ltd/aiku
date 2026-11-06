<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type Severity = "error" | "warning" | "notice"

type CrawlTab = {
    key: string
    label: string
    filters: Array<{ key: string, label: string, count: number, severity: Severity | null }>
}

const props = defineProps<{
    tabs: CrawlTab[]
    totalUrls: number
    activeTab: string
    activeFilter: string | null
    isRunning: boolean
}>()

const emit = defineEmits<{
    (e: "open", tab: string, filter: string): void
}>()

const locale = useLocaleStore()

const groups = computed(() => {
    const issues = props.tabs.flatMap((tab) => tab.filters
        .filter((filter) => filter.severity && filter.count > 0)
        .map((filter) => ({ ...filter, tab: tab.key, tabLabel: tab.label, severity: filter.severity as Severity })))

    return ([
        { severity: "error", label: ctrans("Errors"), dot: "bg-red-500", bar: "bg-red-500" },
        { severity: "warning", label: ctrans("Warnings"), dot: "bg-amber-500", bar: "bg-amber-500" },
        { severity: "notice", label: ctrans("Notices"), dot: "bg-gray-400", bar: "bg-gray-400" },
    ] as const).map((group) => ({
        ...group,
        issues: issues.filter((issue) => issue.severity === group.severity).sort((a, b) => b.count - a.count),
    }))
})

const share = (count: number) => props.totalUrls ? Math.min(100, Math.round(count / props.totalUrls * 1000) / 10) : 0
</script>

<template>
    <aside class="flex min-h-0 flex-col" :aria-label="ctrans('Issues')">
        <div class="flex items-baseline justify-between border-b border-gray-200 px-3 py-2">
            <h2 class="text-xs font-semibold text-gray-900">{{ ctrans("Issues") }}</h2>
            <span class="text-[11px] text-gray-600">{{ ctrans("URLs · % of crawl") }}</span>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-1 py-1">
            <p v-if="isRunning" class="px-2 py-3 text-xs text-gray-600">{{ ctrans("Issues are checked when the crawl finishes.") }}</p>
            <p v-else-if="groups.every((group) => !group.issues.length)" class="px-2 py-3 text-xs text-gray-600">{{ ctrans("The crawl found no issue.") }}</p>
            <template v-for="group in groups" :key="group.severity">
                <section v-if="group.issues.length" class="mb-2">
                    <h3 class="flex items-center gap-1.5 px-2 pb-0.5 pt-1.5 text-[11px] font-semibold text-gray-700">
                        <span class="h-2 w-2 rounded-full" :class="group.dot" aria-hidden="true" />
                        {{ group.label }}
                        <span class="font-normal tabular-nums text-gray-600">{{ locale.number(group.issues.length) }}</span>
                    </h3>
                    <ul>
                        <li v-for="issue in group.issues" :key="`${issue.tab}-${issue.key}`">
                            <button
                                type="button"
                                class="group w-full cursor-pointer rounded px-2 py-1 text-left transition-colors duration-150 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                                :class="activeTab === issue.tab && activeFilter === issue.key ? 'bg-[var(--app-accent-soft)]' : ''"
                                :aria-current="activeTab === issue.tab && activeFilter === issue.key ? 'true' : undefined"
                                @click="emit('open', issue.tab, issue.key)"
                            >
                                <span class="flex items-baseline gap-2 text-xs">
                                    <span class="min-w-0 flex-1 text-gray-900">{{ issue.label }}</span>
                                    <span class="shrink-0 tabular-nums text-gray-800">{{ locale.number(issue.count) }}</span>
                                    <span class="w-10 shrink-0 text-right tabular-nums text-gray-600">{{ locale.number(share(issue.count)) }}%</span>
                                </span>
                                <span class="mt-0.5 flex items-center gap-2">
                                    <span class="text-[11px] text-gray-600">{{ issue.tabLabel }}</span>
                                    <span class="h-1 flex-1 rounded-full bg-gray-100" aria-hidden="true">
                                        <span class="block h-1 rounded-full" :class="group.bar" :style="{ width: `${Math.max(2, share(issue.count))}%` }" />
                                    </span>
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </aside>
</template>
