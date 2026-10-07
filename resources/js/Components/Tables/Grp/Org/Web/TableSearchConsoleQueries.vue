<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"

type SearchQueryRow = {
    query: string
    clicks: number
    impressions: number
    ctr: number
    position: number | null
    pages: number
}

defineProps<{
    data: object
    tab: string
}>()

const locale = useLocaleStore()
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(query)="{ item: searchQuery }: { item: SearchQueryRow }">
            <span class="break-words text-gray-900">{{ searchQuery.query }}</span>
        </template>

        <template #cell(clicks)="{ item: searchQuery }: { item: SearchQueryRow }">
            <span class="tabular-nums">{{ locale.number(searchQuery.clicks) }}</span>
        </template>

        <template #cell(impressions)="{ item: searchQuery }: { item: SearchQueryRow }">
            <span class="tabular-nums">{{ locale.number(searchQuery.impressions) }}</span>
        </template>

        <template #cell(ctr)="{ item: searchQuery }: { item: SearchQueryRow }">
            <span class="tabular-nums">{{ locale.number(searchQuery.ctr) }}%</span>
        </template>

        <template #cell(position)="{ item: searchQuery }: { item: SearchQueryRow }">
            <span v-if="searchQuery.position !== null" class="tabular-nums">{{ locale.number(searchQuery.position) }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(pages)="{ item: searchQuery }: { item: SearchQueryRow }">
            <span class="tabular-nums">{{ locale.number(searchQuery.pages) }}</span>
        </template>
    </Table>
</template>
