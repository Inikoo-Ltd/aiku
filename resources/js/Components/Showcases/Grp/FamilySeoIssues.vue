<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faExclamationTriangle, faInfoCircle, faCheck } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { crawlIssueDetailText, pathOfUrl } from "@/Composables/crawlIssueDetails"

interface IssueItem {
    title: string
    type: string
    severity: "error" | "warning" | "notice"
    severity_label: string
    details: Record<string, any>
    url: string | null
    places: string[]
}

defineProps<{
    data: {
        last_crawl_at: string | null
        total: number
        groups: { key: string, label: string, description: string, items: IssueItem[] }[]
    }
}>()

const severityLook = {
    error: { border: "border-l-red-600", chip: "bg-red-50 text-red-700", icon: faTimes },
    warning: { border: "border-l-amber-500", chip: "bg-amber-50 text-amber-700", icon: faExclamationTriangle },
    notice: { border: "border-l-gray-400", chip: "bg-gray-100 text-gray-600", icon: faInfoCircle },
}

const foundText = (item: IssueItem) => item.url
    ? `${pathOfUrl(item.url)} (${item.details.status_code ?? item.details.error ?? ctrans("not loaded")})`
    : crawlIssueDetailText(item.type, item.details)
</script>

<template>
    <div class="flex flex-col gap-4">
        <p class="text-xs text-gray-500">
            <template v-if="data.last_crawl_at">{{ ctrans("From the site audit of :date", { date: useFormatTime(data.last_crawl_at, { formatTime: "hm" }) }) }}</template>
            <template v-else>{{ ctrans("Not crawled yet. Issues show here after the next site audit of the website.") }}</template>
        </p>

        <div v-if="data.last_crawl_at && !data.groups.length"
            class="flex items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-6 text-sm font-semibold text-green-700">
            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
            {{ ctrans("The last site audit found no issues on this page.") }}
        </div>

        <section v-for="group in data.groups" :key="group.key" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <div class="flex flex-col gap-0.5">
                <h2 class="text-base font-semibold text-gray-900">
                    {{ group.label }}
                    <span class="font-normal text-gray-500">({{ group.items.length }})</span>
                </h2>
                <p class="text-xs text-gray-500">{{ group.description }}</p>
            </div>

            <article v-for="(item, itemIndex) in group.items" :key="itemIndex"
                class="flex flex-col gap-1 rounded-md border border-l-4 border-gray-200 p-3" :class="severityLook[item.severity].border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold text-gray-900">{{ item.title }}</h3>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" :class="severityLook[item.severity].chip">
                        <FontAwesomeIcon :icon="severityLook[item.severity].icon" fixed-width aria-hidden="true" />
                        {{ item.severity_label }}
                    </span>
                </div>
                <template v-if="item.places?.length">
                    <span class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Where on the page") }}</span>
                    <ul class="flex flex-col gap-0.5 text-xs text-gray-700">
                        <li v-for="place in item.places" :key="place">{{ place }}</li>
                    </ul>
                </template>
                <template v-if="foundText(item)">
                    <span class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("What we found") }}</span>
                    <div class="break-all rounded bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700">{{ foundText(item) }}</div>
                </template>
            </article>
        </section>
    </div>
</template>
