<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SiteCrawlerLinkGraph from "@/Components/Seo/SiteCrawlerLinkGraph.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronRight, faSpider } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faSpider)

type Folder = {
    path: string | null
    name: string
    pages: number
    indexable: number
    errors: number
    children: Folder[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    structure: {
        pages: number
        depths: Array<{ label: string, depth: number | null, pages: number }>
        tree: Folder[]
    }
    isFullAudit: boolean
    routes: { crawl: routeType, linkGraph: routeType }
}>()

const locale = useLocaleStore()

const maxDepthPages = computed(() => Math.max(1, ...props.structure.depths.map((row) => row.pages)))

const share = (pages: number) => props.structure.pages ? Math.round(pages / props.structure.pages * 1000) / 10 : 0

const expanded = ref<Set<string>>(new Set())

const toggle = (path: string) => {
    const next = new Set(expanded.value)

    if (next.has(path)) {
        next.delete(path)
    } else {
        next.add(path)
    }

    expanded.value = next
}

const visibleRows = computed(() => {
    const rows: Array<Folder & { level: number }> = []

    const walk = (folders: Folder[], level: number) => {
        for (const folder of folders) {
            rows.push({ ...folder, level })

            if (folder.path && expanded.value.has(folder.path)) {
                walk(folder.children, level + 1)
            }
        }
    }

    walk(props.structure.tree, 0)

    return rows
})

const folderHref = (path: string) => route(props.routes.crawl.name, { ...(props.routes.crawl.parameters as object), tab: "internal", search: path })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 pt-3">
        <SiteCrawlerLinkGraph :graphRoute="routes.linkGraph" :crawlRoute="routes.crawl" />
    </div>

    <div class="grid grid-cols-1 gap-6 px-4 pb-6 pt-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <section class="space-y-3" aria-labelledby="depth-title">
            <div>
                <h2 id="depth-title" class="text-base font-semibold text-gray-900">{{ ctrans("Pages by clicks from the home page") }}</h2>
                <p class="text-xs text-gray-600">{{ ctrans(":count pages that answered 200. Pages more than three clicks deep are harder for visitors and search engines to reach.", { count: locale.number(structure.pages) }) }}</p>
            </div>

            <p v-if="!structure.depths.length" class="rounded-md border border-gray-200 p-4 text-sm text-gray-600">{{ ctrans("This crawl has no page that answered 200.") }}</p>

            <table v-else class="w-full text-sm">
                <thead class="whitespace-nowrap text-left text-xs text-gray-600">
                    <tr>
                        <th scope="col" class="py-1 pr-3 font-medium">{{ ctrans("Depth") }}</th>
                        <th scope="col" class="w-full py-1 pr-3 font-medium"><span class="sr-only">{{ ctrans("Share of pages") }}</span></th>
                        <th scope="col" class="py-1 pr-3 text-right font-medium">{{ ctrans("Pages") }}</th>
                        <th scope="col" class="py-1 text-right font-medium">%</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in structure.depths" :key="row.label" :title="ctrans(':label: :pages pages, :share%', { label: row.label, pages: locale.number(row.pages), share: locale.number(share(row.pages)) })" class="hover:bg-gray-50">
                        <th scope="row" class="whitespace-nowrap py-1.5 pr-3 text-left font-normal" :class="row.depth === null || (row.depth ?? 0) > 3 ? 'text-amber-800' : 'text-gray-800'">{{ row.label }}</th>
                        <td class="py-1.5 pr-3">
                            <div class="h-3 rounded-r bg-[var(--app-accent)]" :style="{ width: row.pages ? `max(2px, ${row.pages / maxDepthPages * 100}%)` : '0' }" aria-hidden="true" />
                        </td>
                        <td class="py-1.5 pr-3 text-right tabular-nums text-gray-900">{{ locale.number(row.pages) }}</td>
                        <td class="py-1.5 text-right tabular-nums text-gray-600">{{ locale.number(share(row.pages)) }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!isFullAudit" class="text-xs text-gray-600">{{ ctrans("This crawl was limited, so pages it did not reach are not counted.") }}</p>
        </section>

        <section class="min-w-0 space-y-3" aria-labelledby="tree-title">
            <div>
                <h2 id="tree-title" class="text-base font-semibold text-gray-900">{{ ctrans("Folders") }}</h2>
                <p class="text-xs text-gray-600">{{ ctrans("The first three levels of the URL paths, largest first. Open a folder to see the pages in it.") }}</p>
            </div>

            <p v-if="!structure.tree.length" class="rounded-md border border-gray-200 p-4 text-sm text-gray-600">{{ ctrans("Every crawled page sits at the root of the website.") }}</p>

            <div v-else class="overflow-x-auto rounded-md border border-gray-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="whitespace-nowrap border-b border-gray-200 bg-gray-50 text-left text-xs text-gray-600">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">{{ ctrans("Folder") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Pages") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Indexable") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("With errors") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in visibleRows" :key="`${row.path ?? row.name}-${row.level}`" class="hover:bg-gray-50">
                            <td class="px-3 py-1.5">
                                <div class="flex items-center gap-1" :style="{ paddingLeft: `${row.level * 1.25}rem` }">
                                    <button
                                        v-if="row.path && row.children.length"
                                        type="button"
                                        class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded text-gray-600 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]"
                                        :aria-expanded="expanded.has(row.path)"
                                        :aria-label="expanded.has(row.path) ? ctrans('Close :folder', { folder: row.name }) : ctrans('Open :folder', { folder: row.name })"
                                        @click="toggle(row.path)"
                                    >
                                        <FontAwesomeIcon :icon="expanded.has(row.path) ? faChevronDown : faChevronRight" fixed-width aria-hidden="true" />
                                    </button>
                                    <span v-else class="w-6 shrink-0" aria-hidden="true" />
                                    <Link v-if="row.path" :href="folderHref(row.path)" class="truncate text-gray-900 hover:text-[var(--app-accent)] hover:underline focus-visible:underline focus-visible:outline-none" :title="row.path">{{ row.path }}</Link>
                                    <span v-else class="text-gray-600">{{ row.name }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-900">{{ locale.number(row.pages) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums" :class="row.indexable < row.pages ? 'text-amber-800' : 'text-gray-700'">{{ locale.number(row.indexable) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums" :class="row.errors ? 'font-medium text-red-700' : 'text-gray-500'">{{ locale.number(row.errors) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
