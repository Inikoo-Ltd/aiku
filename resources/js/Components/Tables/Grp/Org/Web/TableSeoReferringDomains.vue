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
import { useLocaleStore } from "@/Stores/locale"

type ReferringDomainRow = {
    id: number
    referring_domain: string
    rank: number | null
    backlinks: number
    is_own_website: boolean
    first_seen: string | null
    lost_at: string | null
    is_new: boolean
}

defineOptions({ inheritAttrs: false })

defineProps<{
    data?: object | null
    tab: string
}>()

const locale = useLocaleStore()

const statuses = [
    { value: "live", label: ctrans("Linking now") },
    { value: "new", label: ctrans("New this week") },
    { value: "lost", label: ctrans("Lost") },
]
</script>

<template>
    <div class="pb-4">
        <SeoBacklinkFilters :tab="tab" :statuses="statuses" />

        <Table v-if="data" :resource="data" :name="tab">
            <template #cell(referring_domain)="{ item: referringDomain }: { item: ReferringDomainRow }">
                <a
                    :href="`https://${referringDomain.referring_domain}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                    {{ referringDomain.referring_domain }}
                </a>
                <span v-if="referringDomain.is_own_website" class="ml-2 text-xs text-gray-500">{{ ctrans("Our website") }}</span>
            </template>

            <template #cell(rank)="{ item: referringDomain }: { item: ReferringDomainRow }">
                <span class="tabular-nums">{{ referringDomain.rank ?? "-" }}</span>
            </template>

            <template #cell(backlinks)="{ item: referringDomain }: { item: ReferringDomainRow }">
                <span class="tabular-nums">{{ locale.number(referringDomain.backlinks) }}</span>
            </template>

            <template #cell(first_seen)="{ item: referringDomain }: { item: ReferringDomainRow }">
                <span class="tabular-nums">{{ referringDomain.first_seen ? useFormatTime(referringDomain.first_seen) : "-" }}</span>
            </template>

            <template #cell(status)="{ item: referringDomain }: { item: ReferringDomainRow }">
                <Tag v-if="referringDomain.lost_at" severity="danger" :value="ctrans('Lost :date', { date: useFormatTime(referringDomain.lost_at) })" />
                <Tag v-else-if="referringDomain.is_new" severity="success" :value="ctrans('New')" />
            </template>
        </Table>
    </div>
</template>
