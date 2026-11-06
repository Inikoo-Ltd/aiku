<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { crawlIssueDetailText, pathOfUrl } from "@/Composables/crawlIssueDetails"
import { useLocaleStore } from "@/Stores/locale"

export type CrawlTableColumn = { key: string, label: string, numeric: boolean }

type CrawlRow = Record<string, any> & { url: string, url_hash: string }

const props = defineProps<{
    data: object
    columns: CrawlTableColumn[]
    selectedHash: string | null
    filter: string | null
    websiteHost: string
}>()

const emit = defineEmits<{
    (e: "select", urlHash: string | null): void
}>()

const locale = useLocaleStore()

const displayUrl = (url: string) => {
    try {
        const parsed = new URL(url)

        return parsed.host.replace(/^www\./, "") === props.websiteHost ? pathOfUrl(url) : parsed.host + pathOfUrl(url)
    } catch {
        return url
    }
}

const formatValue = (column: CrawlTableColumn, value: any) => {
    if (value === null || value === undefined || value === "") {
        return ""
    }

    if (column.key === "indexability") {
        return value ? ctrans("Indexable") : ctrans("Non-indexable")
    }

    if (column.key === "is_in_sitemap") {
        return value ? ctrans("Yes") : ctrans("No")
    }

    if (column.key === "details") {
        return crawlIssueDetailText(props.filter ?? "", value)
    }

    if (["redirect_to", "canonical", "pagination_next", "pagination_prev"].includes(column.key)) {
        return displayUrl(value)
    }

    return column.numeric ? locale.number(value) : value
}

const cellClass = (column: CrawlTableColumn, value: any) => {
    if (column.key === "status_code") {
        if (value === null || value === undefined) {
            return "text-gray-600"
        }

        return value >= 400 ? "text-red-700 font-medium" : value >= 300 ? "text-amber-800 font-medium" : "text-green-700"
    }

    if (column.key === "indexability" && value === false) {
        return "text-red-700"
    }

    return "text-gray-800"
}

const rowColor = (row: CrawlRow) => row.url_hash === props.selectedHash ? "bg-[var(--app-accent-soft)]" : ""
</script>

<template>
    <Table :resource="data" :rowColorFunction="rowColor">
        <template #cell(url)="{ item: row }: { item: CrawlRow }">
            <button type="button" class="primaryLink block max-w-[24rem] cursor-pointer truncate text-left" :title="row.url" :aria-pressed="row.url_hash === selectedHash" @click="emit('select', row.url_hash === selectedHash ? null : row.url_hash)">
                {{ displayUrl(row.url) }}
            </button>
        </template>

        <template v-for="column in columns" :key="column.key" #[`cell(${column.key})`]="{ item: row }: { item: CrawlRow }">
            <span class="block" :class="[cellClass(column, row[column.key]), column.numeric ? 'whitespace-nowrap tabular-nums' : 'max-w-[16rem] truncate']" :title="String(formatValue(column, row[column.key]))">{{ formatValue(column, row[column.key]) }}</span>
        </template>
    </Table>
</template>
