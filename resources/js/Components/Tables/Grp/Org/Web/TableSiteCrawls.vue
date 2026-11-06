<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

type CrawlRow = {
    id: number
    name: string | null
    website_domain: string
    state: string
    finish_reason: string | null
    trigger: string
    start_at: string | null
    urls_processed: number
    urls_found: number
    health_score: number | null
    errors: number
    warnings: number
    notices: number
    user_name: string | null
    is_full_audit: boolean
}

defineProps<{
    data: object
}>()

const locale = useLocaleStore()

const finishReasons: Record<string, { label: string, class: string }> = {
    completed: { label: ctrans("Completed"), class: "bg-green-50 text-green-700" },
    max_pages_reached: { label: ctrans("URL limit reached"), class: "bg-gray-100 text-gray-700" },
    interrupted: { label: ctrans("Stopped"), class: "bg-amber-50 text-amber-800" },
    failed: { label: ctrans("Failed"), class: "bg-red-50 text-red-700" },
}

const triggers: Record<string, string> = {
    command: ctrans("Automatic"),
    schedule: ctrans("Scheduled"),
    deployment: ctrans("After a deploy"),
    website_update: ctrans("After a website change"),
}

const healthClass = (score: number | null) => {
    if (score === null) {
        return "text-gray-500"
    }

    return score >= 90 ? "text-green-700" : score >= 70 ? "text-amber-700" : "text-red-700"
}
</script>

<template>
    <Table :resource="data">
        <template #cell(website_domain)="{ item: crawl }: { item: CrawlRow }">
            <Link :href="route('grp.websites.crawler.show', [crawl.id])" class="group block focus-visible:outline-none">
                <span class="primaryLink whitespace-nowrap">{{ crawl.website_domain }}</span>
                <span class="block text-xs text-gray-600">
                    {{ crawl.name ?? `#${crawl.id}` }}<template v-if="!crawl.is_full_audit"> · {{ ctrans("Limited") }}</template>
                </span>
            </Link>
        </template>

        <template #cell(state)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.state !== 'finish'" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded bg-[var(--app-accent-soft)] px-1.5 py-0.5 text-xs font-medium text-[var(--app-accent-deep)]">
                <LoadingIcon />
                {{ crawl.state === "ready" ? ctrans("Waiting") : ctrans("Crawling") }}
            </span>
            <span v-else class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium" :class="finishReasons[crawl.finish_reason ?? '']?.class ?? 'bg-gray-100 text-gray-700'">
                {{ finishReasons[crawl.finish_reason ?? ""]?.label ?? crawl.finish_reason }}
            </span>
        </template>

        <template #cell(start_at)="{ item: crawl }: { item: CrawlRow }">
            <span class="whitespace-nowrap">{{ crawl.start_at ? useFormatTime(crawl.start_at, { formatTime: "hm" }) : "-" }}</span>
        </template>

        <template #cell(urls_processed)="{ item: crawl }: { item: CrawlRow }">
            <span class="tabular-nums">{{ locale.number(crawl.urls_processed) }}</span>
        </template>

        <template #cell(health_score)="{ item: crawl }: { item: CrawlRow }">
            <span class="font-medium tabular-nums" :class="healthClass(crawl.health_score)">{{ crawl.health_score === null ? "-" : locale.number(crawl.health_score) }}</span>
        </template>

        <template #cell(issues)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.state === 'finish'" class="inline-flex items-center gap-3 whitespace-nowrap tabular-nums">
                <span class="inline-flex items-center gap-1" :title="ctrans('Errors')"><span class="h-2 w-2 rounded-full bg-red-500" aria-hidden="true" />{{ locale.number(crawl.errors) }}</span>
                <span class="inline-flex items-center gap-1" :title="ctrans('Warnings')"><span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true" />{{ locale.number(crawl.warnings) }}</span>
                <span class="inline-flex items-center gap-1" :title="ctrans('Notices')"><span class="h-2 w-2 rounded-full bg-gray-400" aria-hidden="true" />{{ locale.number(crawl.notices) }}</span>
            </span>
            <span v-else class="text-gray-500">-</span>
        </template>

        <template #cell(user_name)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.user_name">{{ crawl.user_name }}</span>
            <span v-else class="text-gray-600">{{ triggers[crawl.trigger] ?? crawl.trigger }}</span>
        </template>
    </Table>
</template>
