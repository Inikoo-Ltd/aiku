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

const pathOf = (url: string) => {
    try {
        const parsed = new URL(url)

        return parsed.pathname + parsed.search
    } catch {
        return url
    }
}

const detailText = (page: IssuePageRow) => {
    const details = page.details ?? {}

    switch (props.issueType) {
        case "http_4xx":
        case "http_5xx":
        case "linked_redirect":
            return details.is_in_sitemap ? ctrans("Listed in the sitemap") : ""
        case "fetch_failed":
            return details.error ?? ""
        case "redirect_chain":
            return ctrans(":hops hops, ends at :url", { hops: details.hops, url: pathOf(details.final_url ?? "") })
        case "redirect_loop":
            return (details.chain ?? []).map(pathOf).join(" > ")
        case "duplicate_title":
        case "duplicate_meta_description":
            return ctrans("Same as :count other pages", { count: locale.number(details.duplicates ?? 0) })
        case "title_too_long":
        case "title_too_short":
            return ctrans(":length characters: :title", { length: details.length, title: details.title })
        case "meta_description_too_long":
        case "meta_description_too_short":
            return ctrans(":length characters", { length: details.length })
        case "multiple_h1":
            return ctrans(":count h1 headings", { count: details.h1_count })
        case "images_without_alt":
            return ctrans(":count images", { count: details.images })
        case "slow_response":
            return ctrans(":seconds s", { seconds: locale.number(Math.round((details.response_ms ?? 0) / 100) / 10) })
        case "canonical_to_broken":
            return ctrans(":url answers :status", { url: pathOf(details.canonical ?? ""), status: details.status_code ?? "-" })
        case "canonical_to_other_page":
            return pathOf(details.canonical ?? "")
        case "noindex_in_sitemap":
            return details.robots_meta ?? ""
        case "hreflang_invalid_code":
        case "hreflang_conflicting_code":
            return (details.codes ?? []).join(", ")
        case "hreflang_missing_self":
            return ctrans("Lists :count other versions", { count: locale.number(details.alternates ?? 0) })
        case "hreflang_to_broken":
            return (details.alternates ?? []).map((alternate: { url: string, status_code: number | null }) => ctrans(":url answers :status", { url: alternate.url, status: alternate.status_code ?? "-" })).join(", ")
        case "hreflang_missing_return":
            return (details.alternates ?? []).join(", ")
        default:
            return ""
    }
}

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
