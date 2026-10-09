<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Tag from "primevue/tag"
import Table from "@/Components/Table/Table.vue"
import SeoBacklinkFilters from "@/Components/Seo/SeoBacklinkFilters.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

type BacklinkRow = {
    id: number
    source_url: string
    source_domain: string
    source_title: string | null
    domain_rank: number | null
    is_own_website: boolean
    target_url: string
    target_path: string
    target_webpage_code: string | null
    anchor: string | null
    link_type: string | null
    is_dofollow: boolean
    is_broken: boolean
    target_status_code: number | null
    first_seen: string | null
    last_seen: string | null
    lost_at: string | null
    is_new: boolean
}

defineOptions({ inheritAttrs: false })

defineProps<{
    data?: object | null
    tab: string
}>()

const statuses = [
    { value: "live", label: ctrans("Linking now") },
    { value: "new", label: ctrans("New, last 30 days") },
    { value: "lost", label: ctrans("Lost, last 30 days") },
    { value: "broken", label: ctrans("Broken") },
]
</script>

<template>
    <div class="pb-4">
        <SeoBacklinkFilters :tab="tab" :statuses="statuses" />

        <Table v-if="data" :resource="data" :name="tab">
            <template #cell(source_url)="{ item: backlink }: { item: BacklinkRow }">
                <div class="max-w-md">
                    <a
                        :href="backlink.source_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block truncate text-gray-900 underline-offset-2 hover:underline focus-visible:underline"
                        :title="backlink.source_url">
                        {{ backlink.source_title || backlink.source_url }}
                    </a>
                    <div class="truncate text-xs text-gray-500">
                        {{ backlink.source_domain }}
                        <span v-if="backlink.is_own_website">· {{ ctrans("Our website") }}</span>
                    </div>
                </div>
            </template>

            <template #cell(domain_rank)="{ item: backlink }: { item: BacklinkRow }">
                <span class="tabular-nums">{{ backlink.domain_rank ?? "-" }}</span>
            </template>

            <template #cell(anchor)="{ item: backlink }: { item: BacklinkRow }">
                <div class="max-w-xs">
                    <div class="truncate text-gray-700" :title="backlink.anchor ?? undefined">{{ backlink.anchor || (backlink.link_type === "image" ? ctrans("Image") : "-") }}</div>
                    <div class="mt-0.5 flex flex-wrap gap-1">
                        <Tag v-if="!backlink.is_dofollow" severity="secondary" value="nofollow" />
                        <Tag v-if="backlink.lost_at" severity="danger" :value="ctrans('Lost :date', { date: useFormatTime(backlink.lost_at) })" />
                        <Tag v-else-if="backlink.is_broken" severity="warn" :value="ctrans('Broken, our page answers :code', { code: backlink.target_status_code ?? '?' })" />
                        <Tag v-else-if="backlink.is_new" severity="success" :value="ctrans('New')" />
                    </div>
                </div>
            </template>

            <template #cell(target_path)="{ item: backlink }: { item: BacklinkRow }">
                <a
                    :href="backlink.target_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block max-w-xs truncate text-[--app-accent] underline-offset-2 hover:underline focus-visible:underline"
                    :title="backlink.target_url">
                    {{ backlink.target_path }}
                </a>
            </template>

            <template #cell(first_seen)="{ item: backlink }: { item: BacklinkRow }">
                <span class="tabular-nums">{{ backlink.first_seen ? useFormatTime(backlink.first_seen) : "-" }}</span>
            </template>

            <template #cell(last_seen)="{ item: backlink }: { item: BacklinkRow }">
                <span class="tabular-nums">{{ backlink.last_seen ? useFormatTime(backlink.last_seen) : "-" }}</span>
            </template>
        </Table>
    </div>
</template>
