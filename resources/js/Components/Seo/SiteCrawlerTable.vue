<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { nextTick, ref, watch } from "vue"
import Paginator, { PageState } from "primevue/paginator"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { crawlIssueDetailText, pathOfUrl } from "@/Composables/crawlIssueDetails"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSortUp, faSortDown, faSort } from "@fas"

export type CrawlTableColumn = { key: string, label: string, numeric: boolean }

const props = defineProps<{
    columns: CrawlTableColumn[]
    rows: Array<Record<string, any>>
    total: number
    page: number
    perPage: number
    sort: string | null
    desc: boolean
    isLoading: boolean
    selectedHash: string | null
    filter: string | null
    websiteHost: string
    emptyText: string
}>()

const emit = defineEmits<{
    (e: "sort", key: string): void
    (e: "page", page: number, perPage: number): void
    (e: "select", urlHash: string | null): void
}>()

const locale = useLocaleStore()

const body = ref<HTMLElement | null>(null)

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

const statusClass = (status: number | null) => {
    if (status === null || status === undefined) {
        return "text-gray-600"
    }

    return status >= 400 ? "text-red-700 font-medium" : status >= 300 ? "text-amber-800 font-medium" : "text-green-700"
}

const cellClass = (column: CrawlTableColumn, value: any) => {
    if (column.key === "status_code") {
        return statusClass(value)
    }

    if (column.key === "indexability" && value === false) {
        return "text-red-700"
    }

    return "text-gray-800"
}

const ariaSort = (key: string) => props.sort !== key ? "none" : props.desc ? "descending" : "ascending"

const sortIcon = (key: string) => props.sort !== key ? faSort : props.desc ? faSortDown : faSortUp

const rowNumber = (index: number) => (props.page - 1) * props.perPage + index + 1

const focusRow = async (index: number) => {
    await nextTick()
    body.value?.querySelectorAll<HTMLElement>("tr[data-row]")[index]?.focus()
}

const onRowKey = (event: KeyboardEvent, index: number) => {
    if (event.key === "ArrowDown" && index < props.rows.length - 1) {
        event.preventDefault()
        emit("select", props.rows[index + 1].url_hash)
        focusRow(index + 1)
    } else if (event.key === "ArrowUp" && index > 0) {
        event.preventDefault()
        emit("select", props.rows[index - 1].url_hash)
        focusRow(index - 1)
    } else if (event.key === "Enter" || event.key === " ") {
        event.preventDefault()
        emit("select", props.rows[index].url_hash)
    } else if (event.key === "Escape") {
        emit("select", null)
    }
}

const onPage = (state: PageState) => emit("page", state.page + 1, state.rows)

watch(() => props.rows, () => body.value?.closest("[data-scroll]")?.scrollTo({ top: 0 }))

const paginatorPt = {
    root: { class: "!bg-transparent !p-0" },
    pcRowPerPageDropdown: { root: { class: "!h-7 [&_.p-select-label]:!py-0.5 [&_.p-select-label]:!text-xs" } },
    page: { class: "!h-7 !min-w-7 !text-xs" },
    first: { class: "!h-7 !min-w-7" },
    prev: { class: "!h-7 !min-w-7" },
    next: { class: "!h-7 !min-w-7" },
    last: { class: "!h-7 !min-w-7" },
    current: { class: "!text-xs !text-gray-600" },
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div data-scroll class="relative min-h-0 flex-1 overflow-auto">
            <table class="w-full border-separate border-spacing-0 text-[13px] leading-tight">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr class="whitespace-nowrap text-left text-xs text-gray-700">
                        <th scope="col" class="w-12 border-b border-r border-gray-200 px-2 py-1.5 text-right font-medium text-gray-500">#</th>
                        <th scope="col" class="min-w-[14rem] border-b border-r border-gray-200 p-0 font-medium" :aria-sort="ariaSort('url')">
                            <button type="button" class="flex w-full cursor-pointer items-center gap-1.5 px-2 py-1.5 text-left hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]" @click="emit('sort', 'url')">
                                {{ ctrans("Address") }}
                                <FontAwesomeIcon :icon="sortIcon('url')" class="text-[10px]" :class="sort === 'url' ? 'text-[var(--app-accent)]' : 'text-gray-400'" aria-hidden="true" />
                            </button>
                        </th>
                        <th v-for="column in columns" :key="column.key" scope="col" class="border-b border-r border-gray-200 p-0 font-medium last:border-r-0" :aria-sort="column.key === 'details' ? undefined : ariaSort(column.key)">
                            <span v-if="column.key === 'details'" class="block px-2 py-1.5">{{ column.label }}</span>
                            <button
                                v-else
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-1.5 px-2 py-1.5 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                                :class="column.numeric ? 'justify-end' : 'text-left'"
                                @click="emit('sort', column.key)"
                            >
                                {{ column.label }}
                                <FontAwesomeIcon :icon="sortIcon(column.key)" class="text-[10px]" :class="sort === column.key ? 'text-[var(--app-accent)]' : 'text-gray-400'" aria-hidden="true" />
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody ref="body">
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.url_hash"
                        data-row
                        tabindex="0"
                        :aria-selected="row.url_hash === selectedHash"
                        class="cursor-pointer outline-none focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--app-accent)]"
                        :class="row.url_hash === selectedHash ? 'bg-[var(--app-accent-soft)]' : index % 2 ? 'bg-gray-50/60 hover:bg-gray-100' : 'bg-white hover:bg-gray-100'"
                        @click="emit('select', row.url_hash)"
                        @keydown="onRowKey($event, index)"
                    >
                        <td class="border-b border-r border-gray-100 px-2 py-1 text-right tabular-nums text-gray-500">{{ rowNumber(index) }}</td>
                        <td class="max-w-[24rem] border-b border-r border-gray-100 px-2 py-1">
                            <span class="block truncate text-gray-900" :title="row.url">{{ displayUrl(row.url) }}</span>
                        </td>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="border-b border-r border-gray-100 px-2 py-1 last:border-r-0"
                            :class="column.numeric ? 'whitespace-nowrap text-right tabular-nums' : 'max-w-[16rem]'"
                        >
                            <span class="block truncate" :class="cellClass(column, row[column.key])" :title="String(formatValue(column, row[column.key]))">{{ formatValue(column, row[column.key]) }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!rows.length && !isLoading" class="px-4 py-10 text-center text-sm text-gray-600">{{ emptyText }}</p>

            <div v-if="isLoading" class="absolute inset-0 flex items-start justify-center bg-white/60 pt-16" aria-live="polite">
                <LoadingIcon />
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-2 py-1">
            <span class="text-xs tabular-nums text-gray-600">{{ ctrans(":count URLs", { count: locale.number(total) }) }}</span>
            <Paginator
                v-if="total > perPage"
                :rows="perPage"
                :first="(page - 1) * perPage"
                :totalRecords="total"
                :rowsPerPageOptions="[50, 100, 200]"
                template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
                :currentPageReportTemplate="ctrans('{first} to {last} of {totalRecords}')"
                :pt="paginatorPt"
                @page="onPage"
            />
        </div>
    </div>
</template>
