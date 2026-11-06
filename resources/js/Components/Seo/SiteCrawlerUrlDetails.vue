<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { route } from "ziggy-js"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { crawlIssueDetailText, pathOfUrl } from "@/Composables/crawlIssueDetails"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faExternalLink } from "@fal"

type LinkRow = {
    from_url?: string
    to_url?: string
    to_url_hash?: string
    kind: string
    anchor: string | null
    position: string | null
    is_nofollow: boolean
    is_internal?: boolean
    status_code?: number | null
    bytes?: number | null
}

type Severity = "error" | "warning" | "notice"

type UrlDetails = {
    url: string
    type: string
    details: Array<{ label: string, value: string | number, link?: string }>
    serp: { title: string | null, url: string, meta_description: string | null } | null
    issues: Array<{ type: string, label: string, severity: Severity, description: string, how_to_fix: string, details: Record<string, any> | null }>
    inlinks: { rows: LinkRow[], total: number }
    outlinks: { rows: LinkRow[], total: number }
    images: { rows: LinkRow[], total: number }
    headers: Record<string, string>
}

const isWebUrl = (url: string | null | undefined): boolean => /^https?:\/\//i.test(url ?? "")
const props = defineProps<{
    detailsRoute: routeType
    urlHash: string | null
    websiteHost: string
}>()

const emit = defineEmits<{
    (e: "select", urlHash: string): void
    (e: "close"): void
}>()

const locale = useLocaleStore()

const details = ref<UrlDetails | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)
const activeTab = ref("details")

const severityDots: Record<Severity, string> = {
    error: "bg-red-500",
    warning: "bg-amber-500",
    notice: "bg-gray-400",
}

let requestId = 0

const load = async (urlHash: string | null) => {
    if (!urlHash) {
        details.value = null

        return
    }

    const currentRequest = ++requestId
    isLoading.value = true
    error.value = null

    try {
        const response = await axios.get(route(props.detailsRoute.name, props.detailsRoute.parameters), { params: { url_hash: urlHash } })

        if (currentRequest === requestId) {
            details.value = response.data
        }
    } catch {
        if (currentRequest === requestId) {
            error.value = ctrans("The details of this URL could not be loaded. Select it again to retry.")
        }
    } finally {
        if (currentRequest === requestId) {
            isLoading.value = false
        }
    }
}

watch(() => props.urlHash, load, { immediate: true })

const tabs = computed(() => [
    { key: "details", label: ctrans("Details") },
    { key: "issues", label: ctrans("Issues"), count: details.value?.issues.length },
    { key: "inlinks", label: ctrans("Inlinks"), count: details.value?.inlinks.total },
    { key: "outlinks", label: ctrans("Outlinks"), count: details.value?.outlinks.total },
    { key: "images", label: ctrans("Images"), count: details.value?.images.total },
    { key: "headers", label: ctrans("HTTP headers"), count: details.value ? Object.keys(details.value.headers).length : undefined },
    { key: "serp", label: ctrans("Search result") },
])

const headerRows = computed(() => Object.entries(details.value?.headers ?? {}).map(([name, value]) => ({ name, value })))

const serpTitle = computed(() => {
    const title = details.value?.serp?.title ?? ""

    return title.length > 60 ? `${title.slice(0, 57)}...` : title
})

const serpDescription = computed(() => {
    const description = details.value?.serp?.meta_description ?? ""

    return description.length > 160 ? `${description.slice(0, 157)}...` : description
})

const serpBreadcrumb = computed(() => {
    if (!details.value?.serp) {
        return ""
    }

    const url = new URL(details.value.serp.url)

    return [url.host, ...url.pathname.split("/").filter(Boolean)].join(" › ")
})

const displayUrl = (url: string | undefined) => {
    if (!url) {
        return ""
    }

    try {
        const parsed = new URL(url)

        return parsed.host.replace(/^www\./, "") === props.websiteHost ? pathOfUrl(url) : parsed.host + pathOfUrl(url)
    } catch {
        return url
    }
}

const statusClass = (status: number | null | undefined) => {
    if (status === null || status === undefined) {
        return "bg-gray-100 text-gray-600"
    }

    return status >= 400 ? "bg-red-50 text-red-700" : status >= 300 ? "bg-amber-50 text-amber-800" : "bg-green-50 text-green-700"
}

const tabPt = {
    root: { class: "!bg-transparent" },
    activeBar: { class: "!bg-[var(--app-accent)]" },
}

const tabClass = (key: string) => activeTab.value === key ? "!text-[var(--app-accent)]" : ""

const shownOf = (list: { rows: LinkRow[], total: number }) => list.total > list.rows.length
    ? ctrans("Showing the first :shown of :total", { shown: locale.number(list.rows.length), total: locale.number(list.total) })
    : null

const tableClass = "text-xs"
</script>

<template>
    <div class="flex min-h-0 flex-col">
        <div class="flex items-center gap-2 border-b border-gray-200 px-3 pt-2">
            <div class="min-w-0 flex-1">
                <p v-if="details" class="flex items-center gap-2 pb-0.5 text-[13px] font-medium text-gray-900">
                    <span class="truncate" :title="details.url">{{ displayUrl(details.url) }}</span>
                    <a v-if="isWebUrl(details.url)" :href="details.url" target="_blank" rel="noopener noreferrer" class="shrink-0 text-gray-500 hover:text-[var(--app-accent)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]" :aria-label="ctrans('Open this URL in a new tab')">
                        <FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" />
                    </a>
                </p>
                <Tabs v-model:value="activeTab" scrollable :pt="tabPt">
                    <TabList>
                        <Tab v-for="tab in tabs" :key="tab.key" :value="tab.key" class="!px-3 !py-2 !text-xs whitespace-nowrap" :class="tabClass(tab.key)">
                            {{ tab.label }}
                            <span v-if="tab.count !== undefined" class="ml-1 tabular-nums text-gray-500">{{ locale.number(tab.count) }}</span>
                        </Tab>
                    </TabList>
                </Tabs>
            </div>
            <Button type="transparent" size="xs" class="shrink-0 self-start" :tooltip="ctrans('Close details')" @click="emit('close')">
                <template #icon><FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>

        <div class="min-h-0 flex-1 overflow-auto px-3 py-2 text-[13px]">
            <div v-if="isLoading && !details" class="flex justify-center py-8"><LoadingIcon /></div>
            <p v-else-if="error" class="text-xs text-red-600">{{ error }}</p>

            <template v-else-if="details">
                <dl v-if="activeTab === 'details'" class="grid grid-cols-1 gap-x-8 gap-y-1 md:grid-cols-2">
                    <div v-for="detail in details.details" :key="detail.label" class="grid grid-cols-[9.5rem_minmax(0,1fr)] gap-3 border-b border-gray-100 py-0.5">
                        <dt class="text-gray-500">{{ detail.label }}</dt>
                        <dd class="break-words text-gray-900">
                            <a v-if="detail.link && isWebUrl(detail.link)" :href="detail.link" class="primaryLink">{{ detail.value }}</a>
                            <template v-else>{{ typeof detail.value === "number" ? locale.number(detail.value) : detail.value }}</template>
                        </dd>
                    </div>
                </dl>

                <div v-else-if="activeTab === 'issues'">
                    <p v-if="!details.issues.length" class="text-gray-500">{{ ctrans("The crawl found no issue on this URL.") }}</p>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="issue in details.issues" :key="issue.type" class="space-y-1 py-2.5 first:pt-0">
                            <p class="flex items-center gap-2 font-medium text-gray-900">
                                <span class="h-2 w-2 shrink-0 rounded-full" :class="severityDots[issue.severity]" aria-hidden="true" />
                                {{ issue.label }}
                            </p>
                            <p v-if="crawlIssueDetailText(issue.type, issue.details)" class="break-words text-xs text-gray-700">{{ crawlIssueDetailText(issue.type, issue.details) }}</p>
                            <p class="text-xs text-gray-600"><span class="font-medium text-gray-700">{{ ctrans("How to fix") }}:</span> {{ issue.how_to_fix }}</p>
                        </li>
                    </ul>
                </div>

                <div v-else-if="activeTab === 'inlinks'">
                    <p v-if="shownOf(details.inlinks)" class="mb-2 text-xs text-gray-500">{{ shownOf(details.inlinks) }}</p>
                    <DataTable :value="details.inlinks.rows" size="small" rowHover scrollable :class="tableClass">
                        <template #empty><p class="py-4 text-center text-gray-500">{{ ctrans("No crawled page links here.") }}</p></template>
                        <Column field="from_url" :header="ctrans('From')" class="max-w-[24rem]">
                            <template #body="{ data }"><span class="block truncate" :title="data.from_url">{{ displayUrl(data.from_url) }}</span></template>
                        </Column>
                        <Column field="anchor" :header="ctrans('Anchor text')" class="max-w-[16rem]">
                            <template #body="{ data }"><span class="block truncate" :title="data.anchor">{{ data.anchor ?? "-" }}</span></template>
                        </Column>
                        <Column field="kind" :header="ctrans('Type')" headerClass="whitespace-nowrap" />
                        <Column field="position" :header="ctrans('Position')" />
                        <Column field="is_nofollow" :header="ctrans('Follow')">
                            <template #body="{ data }"><span :class="data.is_nofollow ? 'text-amber-700' : 'text-gray-600'">{{ data.is_nofollow ? ctrans("Nofollow") : ctrans("Follow") }}</span></template>
                        </Column>
                    </DataTable>
                </div>

                <div v-else-if="activeTab === 'outlinks' || activeTab === 'images'">
                    <p v-if="shownOf(details[activeTab])" class="mb-2 text-xs text-gray-500">{{ shownOf(details[activeTab]) }}</p>
                    <DataTable :value="details[activeTab].rows" size="small" rowHover scrollable :class="tableClass">
                        <template #empty><p class="py-4 text-center text-gray-500">{{ activeTab === "images" ? ctrans("This page has no images.") : ctrans("This page has no links.") }}</p></template>
                        <Column field="to_url" :header="ctrans('To')" class="max-w-[24rem]">
                            <template #body="{ data }">
                                <button type="button" class="block w-full cursor-pointer truncate text-left text-gray-900 hover:text-[var(--app-accent)] hover:underline focus-visible:underline" :title="data.to_url" @click="emit('select', data.to_url_hash)">{{ displayUrl(data.to_url) }}</button>
                            </template>
                        </Column>
                        <Column field="anchor" :header="activeTab === 'images' ? ctrans('Alt text') : ctrans('Anchor text')" class="max-w-[16rem]">
                            <template #body="{ data }">
                                <span v-if="data.anchor" class="block truncate" :title="data.anchor">{{ data.anchor }}</span>
                                <span v-else :class="activeTab === 'images' ? 'text-red-700' : 'text-gray-500'">{{ activeTab === "images" ? ctrans("Missing") : "-" }}</span>
                            </template>
                        </Column>
                        <Column field="status_code" :header="ctrans('Status')">
                            <template #body="{ data }">
                                <span class="inline-block rounded px-1.5 py-0.5 font-medium tabular-nums" :class="statusClass(data.status_code)">{{ data.status_code ?? ctrans("Not checked") }}</span>
                            </template>
                        </Column>
                        <Column v-if="activeTab === 'images'" field="bytes" :header="ctrans('Size')" class="text-right tabular-nums">
                            <template #body="{ data }">{{ data.bytes === null ? "-" : ctrans(":kb KB", { kb: locale.number(Math.round(data.bytes / 1024)) }) }}</template>
                        </Column>
                        <Column v-if="activeTab === 'outlinks'" field="kind" :header="ctrans('Type')" />
                        <Column field="position" :header="ctrans('Position')" />
                        <Column v-if="activeTab === 'outlinks'" field="is_nofollow" :header="ctrans('Follow')">
                            <template #body="{ data }"><span :class="data.is_nofollow ? 'text-amber-700' : 'text-gray-600'">{{ data.is_nofollow ? ctrans("Nofollow") : ctrans("Follow") }}</span></template>
                        </Column>
                    </DataTable>
                </div>

                <DataTable v-else-if="activeTab === 'headers'" :value="headerRows" size="small" rowHover scrollable :class="tableClass">
                    <template #empty><p class="py-4 text-center text-gray-500">{{ ctrans("No headers were recorded for this URL.") }}</p></template>
                    <Column field="name" :header="ctrans('Header')"><template #body="{ data }"><span class="whitespace-nowrap font-mono">{{ data.name }}</span></template></Column>
                    <Column field="value" :header="ctrans('Value')"><template #body="{ data }"><span class="break-all font-mono">{{ data.value }}</span></template></Column>
                </DataTable>

                <div v-else-if="activeTab === 'serp'">
                    <div v-if="details.serp" class="max-w-xl space-y-1 rounded-md border border-gray-200 p-4">
                        <p class="truncate text-xs text-gray-600">{{ serpBreadcrumb }}</p>
                        <p class="text-lg leading-snug text-[#1a0dab]">{{ serpTitle || ctrans("(no title)") }}</p>
                        <p class="text-sm text-gray-600">{{ serpDescription || ctrans("(no meta description, Google picks text from the page)") }}</p>
                    </div>
                    <p v-else class="text-gray-500">{{ ctrans("Only crawled pages have a search result preview.") }}</p>
                </div>
            </template>
        </div>
    </div>
</template>
