<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { crawlIssueDetailText, pathOfUrl } from "@/Composables/crawlIssueDetails"

type IssuePageRow = {
    url: string
    status_code: number | null
    inlinks: number
    response_ms: number | null
    is_in_sitemap: boolean
    webpage_slug: string | null
    webpage_code: string | null
    details: Record<string, any>
}

const props = defineProps<{
    data: object
    issueType: string
    websiteSlug: string
}>()

const locale = useLocaleStore()

const routeParams = route().params as Record<string, string>

const webpageHref = (page: IssuePageRow) => page.webpage_slug
    ? route("grp.org.shops.show.web.webpages.show", [routeParams.organisation, routeParams.shop, props.websiteSlug, page.webpage_slug])
    : null

const pathOf = pathOfUrl

const detailText = (page: IssuePageRow) => crawlIssueDetailText(props.issueType, page.details)

const linkedFrom = (page: IssuePageRow): string[] => page.details?.linked_from ?? []
</script>

<template>
    <Table :resource="data">
        <template #cell(url)="{ item: page }: { item: IssuePageRow }">
            <a :href="page.url" target="_blank" rel="noopener noreferrer" class="break-all text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                {{ pathOf(page.url) }}
            </a>
        </template>

        <template #cell(webpage_code)="{ item: page }: { item: IssuePageRow }">
            <Link v-if="webpageHref(page)" :href="webpageHref(page)" class="primaryLink">
                {{ page.webpage_code }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(status_code)="{ item: page }: { item: IssuePageRow }">
            <span class="tabular-nums">{{ page.status_code ?? "-" }}</span>
        </template>

        <template #cell(inlinks)="{ item: page }: { item: IssuePageRow }">
            <span class="tabular-nums">{{ locale.number(page.inlinks) }}</span>
        </template>

        <template #cell(details)="{ item: page }: { item: IssuePageRow }">
            <div class="max-w-xl space-y-1 text-gray-700">
                <p v-if="detailText(page)" class="break-words">{{ detailText(page) }}</p>
                <a v-if="page.details?.sentry_url" :href="page.details.sentry_url" target="_blank" rel="noopener noreferrer" class="text-xs underline underline-offset-2">
                    {{ ctrans("Errors in Sentry") }}
                </a>
                <details v-if="linkedFrom(page).length" class="text-xs text-gray-600">
                    <summary class="cursor-pointer">{{ ctrans("Linked from :count pages", { count: locale.number(page.inlinks) }) }}</summary>
                    <ul class="mt-1 space-y-0.5">
                        <li v-for="sourceUrl in linkedFrom(page)" :key="sourceUrl" class="break-all">
                            <a :href="sourceUrl" target="_blank" rel="noopener noreferrer" class="underline-offset-2 hover:underline">{{ pathOf(sourceUrl) }}</a>
                        </li>
                    </ul>
                </details>
            </div>
        </template>
    </Table>
</template>
