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

const finishReasons: Record<string, string> = {
    completed: ctrans("Completed"),
    max_pages_reached: ctrans("Limit reached"),
    interrupted: ctrans("Stopped"),
    failed: ctrans("Failed"),
}
</script>

<template>
    <Table :resource="data">
        <template #cell(state)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.state !== 'finish'" class="inline-flex items-center gap-1.5 text-[var(--app-accent)]">
                <LoadingIcon />
                {{ crawl.state === "ready" ? ctrans("Waiting") : ctrans("Running") }}
            </span>
            <span v-else :class="crawl.finish_reason === 'failed' ? 'text-red-600' : 'text-gray-600'">
                {{ finishReasons[crawl.finish_reason ?? ""] ?? crawl.finish_reason }}
            </span>
        </template>

        <template #cell(website_domain)="{ item: crawl }: { item: CrawlRow }">
            <Link :href="route('grp.websites.crawler.show', [crawl.id])" class="primaryLink whitespace-nowrap">
                {{ crawl.website_domain }}
            </Link>
        </template>

        <template #cell(name)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.name">{{ crawl.name }}</span>
            <span v-else class="text-gray-400">#{{ crawl.id }}</span>
            <span v-if="!crawl.is_full_audit" class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600" :title="ctrans('Limited by pages, depth, patterns or skipped checks: not used for the health score and page scores')">{{ ctrans("Limited") }}</span>
        </template>

        <template #cell(start_at)="{ item: crawl }: { item: CrawlRow }">
            <span class="whitespace-nowrap">{{ crawl.start_at ? useFormatTime(crawl.start_at, { formatTime: "hm" }) : "-" }}</span>
        </template>

        <template #cell(urls_processed)="{ item: crawl }: { item: CrawlRow }">
            <span class="tabular-nums">{{ locale.number(crawl.urls_processed) }}</span>
        </template>

        <template #cell(health_score)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.health_score !== null" class="tabular-nums">{{ locale.number(crawl.health_score) }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(issues)="{ item: crawl }: { item: CrawlRow }">
            <span class="whitespace-nowrap tabular-nums">
                <span class="text-red-600">{{ locale.number(crawl.errors) }}</span>
                <span class="text-gray-300"> / </span>
                <span class="text-amber-600">{{ locale.number(crawl.warnings) }}</span>
                <span class="text-gray-300"> / </span>
                <span class="text-gray-500">{{ locale.number(crawl.notices) }}</span>
            </span>
        </template>

        <template #cell(user_name)="{ item: crawl }: { item: CrawlRow }">
            <span v-if="crawl.user_name">{{ crawl.user_name }}</span>
            <span v-else class="text-gray-400">{{ ctrans("Scheduled") }}</span>
        </template>
    </Table>
</template>
