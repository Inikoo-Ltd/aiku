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
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { crawlIssueDetailText } from "@/Composables/crawlIssueDetails"
import { routeType } from "@/types/route"

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

type UrlDetails = {
    url: string
    type: string
    details: Array<{ label: string, value: string | number, link?: string }>
    serp: { title: string | null, url: string, meta_description: string | null } | null
    issues: Array<{ type: string, label: string, severity: "error" | "warning" | "notice", description: string, how_to_fix: string, details: Record<string, any> | null }>
    inlinks: { rows: LinkRow[], total: number }
    outlinks: { rows: LinkRow[], total: number }
    images: { rows: LinkRow[], total: number }
    headers: Record<string, string>
}

const props = defineProps<{
    detailsRoute: routeType
    urlHash: string | null
}>()

const emit = defineEmits<{
    (e: "select", urlHash: string): void
}>()

const locale = useLocaleStore()

const details = ref<UrlDetails | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)
const activeTab = ref("details")

const severityDots: Record<string, string> = {
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
            error.value = ctrans("The details of this URL could not be loaded")
        }
    } finally {
        if (currentRequest === requestId) {
            isLoading.value = false
        }
    }
}

watch(() => props.urlHash, load, { immediate: true })

const tabs = computed(() => [
    { key: "details", label: ctrans("URL details") },
    { key: "issues", label: ctrans("Issues"), count: details.value?.issues.length },
    { key: "inlinks", label: ctrans("Inlinks"), count: details.value?.inlinks.total },
    { key: "outlinks", label: ctrans("Outlinks"), count: details.value?.outlinks.total },
    { key: "images", label: ctrans("Images"), count: details.value?.images.total },
    { key: "headers", label: ctrans("HTTP headers"), count: details.value ? Object.keys(details.value.headers).length : undefined },
    { key: "serp", label: ctrans("SERP snippet") },
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

const tabPt = {
    root: { class: "!bg-transparent" },
    activeBar: { class: "!bg-[var(--app-accent)]" },
}

const tabClass = (key: string) => activeTab.value === key ? "!text-[var(--app-accent)] !border-[var(--app-accent)]" : ""

const shownOf = (list: { rows: LinkRow[], total: number }) => list.total > list.rows.length
    ? ctrans("Showing :shown of :total", { shown: locale.number(list.rows.length), total: locale.number(list.total) })
    : null
</script>

<template>
    <section class="flex min-h-0 flex-col border-t border-gray-200 bg-white">
        <div class="flex items-center justify-between gap-3 overflow-x-auto px-3">
            <Tabs v-model:value="activeTab" scrollable :pt="tabPt">
                <TabList>
                    <Tab v-for="tab in tabs" :key="tab.key" :value="tab.key" class="!px-3 !py-2 !text-xs whitespace-nowrap" :class="tabClass(tab.key)">
                        {{ tab.label }}
                        <span v-if="tab.count !== undefined" class="ml-1 tabular-nums text-gray-400">{{ locale.number(tab.count) }}</span>
                    </Tab>
                </TabList>
            </Tabs>
            <span v-if="details" class="truncate font-mono text-xs text-gray-500" :title="details.url">{{ details.url }}</span>
        </div>

        <div class="min-h-0 flex-1 overflow-auto p-3 text-sm">
            <p v-if="!urlHash" class="text-gray-500">{{ ctrans("Select a URL in the table to see its details, links and issues.") }}</p>
            <div v-else-if="isLoading && !details" class="flex justify-center py-6"><LoadingIcon /></div>
            <p v-else-if="error" class="text-xs text-red-500">{{ error }}</p>

            <template v-else-if="details">
                <dl v-if="activeTab === 'details'" class="grid grid-cols-[minmax(10rem,auto)_1fr] gap-x-4 gap-y-1">
                    <template v-for="detail in details.details" :key="detail.label">
                        <dt class="whitespace-nowrap text-gray-500">{{ detail.label }}</dt>
                        <dd class="break-all text-gray-900">
                            <a v-if="detail.link" :href="detail.link" class="primaryLink">{{ detail.value }}</a>
                            <template v-else>{{ typeof detail.value === "number" ? locale.number(detail.value) : detail.value }}</template>
                        </dd>
                    </template>
                </dl>

                <div v-else-if="activeTab === 'issues'" class="space-y-3">
                    <p v-if="!details.issues.length" class="text-gray-500">{{ ctrans("No issues found on this URL.") }}</p>
                    <div v-for="issue in details.issues" :key="issue.type" class="space-y-1">
                        <p class="flex items-center gap-2 font-medium text-gray-900">
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="severityDots[issue.severity]" aria-hidden="true" />
                            {{ issue.label }}
                        </p>
                        <p class="text-xs text-gray-600">{{ issue.description }}</p>
                        <p class="text-xs text-gray-600"><span class="font-medium text-gray-700">{{ ctrans("How to fix") }}:</span> {{ issue.how_to_fix }}</p>
                        <p v-if="crawlIssueDetailText(issue.type, issue.details)" class="break-all text-xs text-gray-500">{{ crawlIssueDetailText(issue.type, issue.details) }}</p>
                    </div>
                </div>

                <div v-else-if="activeTab === 'inlinks'">
                    <p v-if="shownOf(details.inlinks)" class="mb-2 text-xs text-gray-500">{{ shownOf(details.inlinks) }}</p>
                    <DataTable :value="details.inlinks.rows" size="small" stripedRows scrollable>
                        <Column field="from_url" :header="ctrans('From')">
                            <template #body="{ data }"><span class="break-all font-mono text-xs">{{ data.from_url }}</span></template>
                        </Column>
                        <Column field="kind" :header="ctrans('Type')" />
                        <Column field="anchor" :header="ctrans('Anchor text')" />
                        <Column field="position" :header="ctrans('Link position')" />
                        <Column field="is_nofollow" :header="ctrans('Follow')">
                            <template #body="{ data }">{{ data.is_nofollow ? ctrans("Nofollow") : ctrans("Follow") }}</template>
                        </Column>
                    </DataTable>
                </div>

                <div v-else-if="activeTab === 'outlinks' || activeTab === 'images'">
                    <p v-if="shownOf(details[activeTab])" class="mb-2 text-xs text-gray-500">{{ shownOf(details[activeTab]) }}</p>
                    <DataTable :value="details[activeTab].rows" size="small" stripedRows scrollable>
                        <Column field="to_url" :header="ctrans('To')">
                            <template #body="{ data }">
                                <button type="button" class="break-all text-left font-mono text-xs hover:underline focus-visible:underline" @click="emit('select', data.to_url_hash)">{{ data.to_url }}</button>
                            </template>
                        </Column>
                        <Column v-if="activeTab === 'outlinks'" field="kind" :header="ctrans('Type')" />
                        <Column field="anchor" :header="activeTab === 'images' ? ctrans('Alt text') : ctrans('Anchor text')" />
                        <Column field="status_code" :header="ctrans('Status code')">
                            <template #body="{ data }">
                                <span :class="data.status_code >= 400 ? 'text-red-600' : ''">{{ data.status_code ?? "-" }}</span>
                            </template>
                        </Column>
                        <Column v-if="activeTab === 'images'" field="bytes" :header="ctrans('Size (bytes)')">
                            <template #body="{ data }">{{ data.bytes === null ? "-" : locale.number(data.bytes) }}</template>
                        </Column>
                        <Column field="position" :header="ctrans('Link position')" />
                        <Column v-if="activeTab === 'outlinks'" field="is_nofollow" :header="ctrans('Follow')">
                            <template #body="{ data }">{{ data.is_nofollow ? ctrans("Nofollow") : ctrans("Follow") }}</template>
                        </Column>
                    </DataTable>
                </div>

                <DataTable v-else-if="activeTab === 'headers'" :value="headerRows" size="small" stripedRows scrollable>
                    <Column field="name" :header="ctrans('Header')"><template #body="{ data }"><span class="whitespace-nowrap font-mono text-xs">{{ data.name }}</span></template></Column>
                    <Column field="value" :header="ctrans('Value')"><template #body="{ data }"><span class="break-all font-mono text-xs">{{ data.value }}</span></template></Column>
                </DataTable>

                <div v-else-if="activeTab === 'serp'">
                    <div v-if="details.serp" class="max-w-xl space-y-1 rounded border border-gray-200 p-4">
                        <p class="truncate text-xs text-gray-600">{{ serpBreadcrumb }}</p>
                        <p class="text-lg leading-snug text-[#1a0dab]">{{ serpTitle || ctrans("(no title)") }}</p>
                        <p class="text-sm text-gray-600">{{ serpDescription || ctrans("(no meta description, Google picks text from the page)") }}</p>
                    </div>
                    <p v-else class="text-gray-500">{{ ctrans("Only crawled pages have a search result snippet.") }}</p>
                </div>
            </template>
        </div>
    </section>
</template>
