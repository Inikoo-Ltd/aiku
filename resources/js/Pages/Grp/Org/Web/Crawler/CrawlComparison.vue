<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Select from "primevue/select"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { faSpider } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faSpider)

type Severity = "error" | "warning" | "notice"

type CrawlSummary = {
    id: number
    name: string | null
    end_at: string | null
    urls_processed: number
    health_score: number | null
    errors: number
    warnings: number
    notices: number
    is_full_audit: boolean
}

type IssueChange = {
    type: string
    label: string
    severity: Severity
    tab: string
    previous: number
    current: number
    added: number
    resolved: number
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    current: CrawlSummary
    previous: CrawlSummary | null
    others: Array<{ id: number, label: string }>
    issues: IssueChange[]
    selected: { type: string, label: string, side: "added" | "resolved", rows: string[], total: number } | null
    routes: { self: routeType, crawl: routeType }
}>()

const locale = useLocaleStore()

const severityDots: Record<Severity, string> = {
    error: "bg-red-500",
    warning: "bg-amber-500",
    notice: "bg-gray-400",
}

const isLoading = ref(false)

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)]"

const visit = (query: Record<string, string | number | undefined>) => router.get(route(props.routes.self.name, props.routes.self.parameters), query, {
    preserveScroll: true,
    preserveState: true,
    onStart: () => isLoading.value = true,
    onFinish: () => isLoading.value = false,
})

const compareWith = (crawlId: number) => visit({ with: crawlId })

const openUrls = (issue: IssueChange, side: "added" | "resolved") => visit({ with: props.previous?.id, type: issue.type, side })

const summaryRows = computed(() => {
    if (!props.previous) {
        return []
    }

    return [
        { label: ctrans("URLs crawled"), before: props.previous.urls_processed, now: props.current.urls_processed, isBetterWhenLower: false },
        { label: ctrans("Health score"), before: props.previous.health_score, now: props.current.health_score, isBetterWhenLower: false },
        { label: ctrans("Errors"), before: props.previous.errors, now: props.current.errors, isBetterWhenLower: true },
        { label: ctrans("Warnings"), before: props.previous.warnings, now: props.current.warnings, isBetterWhenLower: true },
        { label: ctrans("Notices"), before: props.previous.notices, now: props.current.notices, isBetterWhenLower: true },
    ]
})

const changeClass = (before: number | null, now: number | null, isBetterWhenLower: boolean) => {
    if (before === null || now === null || before === now) {
        return "text-gray-500"
    }

    return (now < before) === isBetterWhenLower ? "text-green-600" : "text-red-600"
}

const signed = (value: number) => (value > 0 ? "+" : "") + locale.number(value)

const crawlLabel = (crawl: CrawlSummary) => crawl.name || (crawl.end_at ? useFormatTime(crawl.end_at, { formatTime: "hm" }) : `#${crawl.id}`)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4 text-sm">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-gray-600">{{ ctrans("Compare") }}</span>
            <Link :href="route(routes.crawl.name, routes.crawl.parameters)" class="primaryLink">{{ crawlLabel(current) }}</Link>
            <span class="text-gray-600">{{ ctrans("with") }}</span>
            <Select
                :modelValue="previous?.id"
                :options="others"
                optionLabel="label"
                optionValue="id"
                :placeholder="ctrans('Choose a crawl')"
                :class="fieldFocusClass"
                class="w-80"
                @update:modelValue="compareWith"
            />
        </div>

        <p v-if="!previous" class="text-gray-500">{{ ctrans("There is no other finished crawl of this website to compare with.") }}</p>

        <template v-else>
            <p v-if="current.is_full_audit !== previous.is_full_audit" class="rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                {{ ctrans("One of the two crawls is limited, so URLs it did not reach count as fixed or new.") }}
            </p>

            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div v-for="row in summaryRows" :key="row.label" class="rounded border border-gray-200 p-3">
                    <dt class="text-xs text-gray-500">{{ row.label }}</dt>
                    <dd class="mt-1 tabular-nums">
                        <span class="text-gray-500">{{ row.before === null ? "-" : locale.number(row.before) }}</span>
                        <span class="text-gray-500" aria-hidden="true"> → </span>
                        <span class="font-medium text-gray-900">{{ row.now === null ? "-" : locale.number(row.now) }}</span>
                        <span v-if="row.before !== null && row.now !== null && row.before !== row.now" class="ml-1 text-xs" :class="changeClass(row.before, row.now, row.isBetterWhenLower)">
                            ({{ signed(Math.round((row.now - row.before) * 10) / 10) }})
                        </span>
                    </dd>
                </div>
            </dl>

            <DataTable :value="issues" size="small" stripedRows :loading="isLoading" class="text-sm">
                <template #empty>
                    <p class="py-6 text-center text-gray-500">{{ ctrans("Neither crawl found any issue.") }}</p>
                </template>
                <Column field="label" :header="ctrans('Issue')">
                    <template #body="{ data }">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="severityDots[data.severity as Severity]" aria-hidden="true" />
                            {{ data.label }}
                        </span>
                    </template>
                </Column>
                <Column field="tab" :header="ctrans('Tab')" headerClass="whitespace-nowrap" />
                <Column field="previous" :header="ctrans('Before')" class="text-right tabular-nums">
                    <template #body="{ data }">{{ locale.number(data.previous) }}</template>
                </Column>
                <Column field="current" :header="ctrans('Now')" class="text-right tabular-nums">
                    <template #body="{ data }">
                        <span :class="changeClass(data.previous, data.current, true)">{{ locale.number(data.current) }}</span>
                    </template>
                </Column>
                <Column field="added" :header="ctrans('New')" class="text-right tabular-nums">
                    <template #body="{ data }">
                        <button v-if="data.added" type="button" class="text-red-600 hover:underline focus-visible:underline" @click="openUrls(data, 'added')">{{ signed(data.added) }}</button>
                        <span v-else class="text-gray-500">0</span>
                    </template>
                </Column>
                <Column field="resolved" :header="ctrans('Fixed')" class="text-right tabular-nums">
                    <template #body="{ data }">
                        <button v-if="data.resolved" type="button" class="text-green-600 hover:underline focus-visible:underline" @click="openUrls(data, 'resolved')">{{ locale.number(data.resolved) }}</button>
                        <span v-else class="text-gray-500">0</span>
                    </template>
                </Column>
            </DataTable>

            <section v-if="selected" class="space-y-2 rounded border border-gray-200 p-3">
                <h3 class="font-medium text-gray-900">
                    {{ selected.side === "added"
                        ? ctrans(":issue: :count new URLs", { issue: selected.label, count: locale.number(selected.total) })
                        : ctrans(":issue: :count URLs fixed", { issue: selected.label, count: locale.number(selected.total) }) }}
                </h3>
                <p v-if="selected.total > selected.rows.length" class="text-xs text-gray-500">{{ ctrans("Showing the first :count", { count: locale.number(selected.rows.length) }) }}</p>
                <ul class="max-h-80 space-y-0.5 overflow-y-auto font-mono text-xs">
                    <li v-for="url in selected.rows" :key="url" class="break-all">
                        <a :href="url" target="_blank" rel="noopener noreferrer" class="hover:underline focus-visible:underline">{{ url }}</a>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
