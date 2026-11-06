<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { pathOfUrl } from "@/Composables/crawlIssueDetails"

type PageRow = {
    url: string
    status_code: number | null
    redirect_to: string | null
    title: string | null
    meta_description: string | null
    h1: string | null
    is_indexable: boolean
    link_depth: number | null
    inlinks: number
    response_ms: number | null
    word_count: number | null
    webpage_slug: string | null
    issues: number
}

const props = defineProps<{
    data: object
    websiteSlug: string
}>()

const locale = useLocaleStore()

const routeParams = route().params as Record<string, string>

const webpageHref = (page: PageRow) => page.webpage_slug
    ? route("grp.org.shops.show.web.webpages.show", [routeParams.organisation, routeParams.shop, props.websiteSlug, page.webpage_slug])
    : null

const statusClass = (status: number | null) => status === null || status >= 400 ? "text-red-700" : status >= 300 ? "text-amber-700" : "text-gray-900"
</script>

<template>
    <Table :resource="data">
        <template #cell(url)="{ item: page }: { item: PageRow }">
            <div class="max-w-xs">
                <a :href="page.url" target="_blank" rel="noopener noreferrer" class="break-all text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                    {{ pathOfUrl(page.url) }}
                </a>
                <Link v-if="webpageHref(page)" :href="webpageHref(page)!" class="ml-1 text-xs text-gray-500 underline-offset-2 hover:underline">
                    {{ ctrans("in Aiku") }}
                </Link>
                <div v-if="page.redirect_to" class="break-all text-xs text-gray-500">→ {{ pathOfUrl(page.redirect_to) }}</div>
            </div>
        </template>

        <template #cell(status_code)="{ item: page }: { item: PageRow }">
            <span class="tabular-nums" :class="statusClass(page.status_code)">{{ page.status_code ?? ctrans("Failed") }}</span>
        </template>

        <template #cell(title)="{ item: page }: { item: PageRow }">
            <span class="line-clamp-2 max-w-xs" :title="page.title ?? ''">{{ page.title ?? "-" }}</span>
        </template>

        <template #cell(meta_description)="{ item: page }: { item: PageRow }">
            <span class="line-clamp-2 max-w-sm text-gray-600" :title="page.meta_description ?? ''">{{ page.meta_description ?? "-" }}</span>
        </template>

        <template #cell(h1)="{ item: page }: { item: PageRow }">
            <span class="line-clamp-2 max-w-xs">{{ page.h1 ?? "-" }}</span>
        </template>

        <template #cell(is_indexable)="{ item: page }: { item: PageRow }">
            {{ page.is_indexable ? ctrans("Yes") : ctrans("No") }}
        </template>

        <template #cell(link_depth)="{ item: page }: { item: PageRow }">
            <span class="tabular-nums">{{ page.link_depth ?? "-" }}</span>
        </template>

        <template #cell(inlinks)="{ item: page }: { item: PageRow }">
            <span class="tabular-nums">{{ locale.number(page.inlinks) }}</span>
        </template>

        <template #cell(response_ms)="{ item: page }: { item: PageRow }">
            <span class="tabular-nums">{{ page.response_ms === null ? "-" : locale.number(page.response_ms) }}</span>
        </template>

        <template #cell(word_count)="{ item: page }: { item: PageRow }">
            <span class="tabular-nums">{{ page.word_count === null ? "-" : locale.number(page.word_count) }}</span>
        </template>

        <template #cell(issues)="{ item: page }: { item: PageRow }">
            <span class="tabular-nums" :class="page.issues ? 'text-gray-900' : 'text-gray-400'">{{ locale.number(page.issues) }}</span>
        </template>
    </Table>
</template>
