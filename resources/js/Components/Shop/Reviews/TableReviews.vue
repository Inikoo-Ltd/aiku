<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { computed } from "vue"

type ReviewTablePayload = TableTS & {
    stats?: {
        total?: number
        average_rating?: number
        verified?: number
        helpful_count?: number
        status_approved?: number
        status_pending?: number
        status_rejected?: number
    }
}

const props = defineProps<{
    data: ReviewTablePayload
    tab?: string
}>()

const renderStars = (rating: number): string => {
    const value = Number.isFinite(rating) ? Math.max(0, Math.min(5, rating)) : 0
    return "★".repeat(value)
}

const stats = computed(() => {
    const backendStats = props.data?.stats ?? {}
    return {
        total: Number(backendStats.total ?? 0),
        averageRating: Number(backendStats.average_rating ?? 0).toFixed(1),
        verified: Number(backendStats.verified ?? 0),
        helpfulCount: Number(backendStats.helpful_count ?? 0),
        statusApproved: Number(backendStats.status_approved ?? 0),
        statusPending: Number(backendStats.status_pending ?? 0),
        statusRejected: Number(backendStats.status_rejected ?? 0),
    }
})
</script>

<template>
    <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-10">
        <div class="xl:col-span-7">
            <Table :resource="data" :name="tab">
                <template #cell(rating)="{ item }">
                    <span class="text-amber-500">{{ renderStars(item.rating) }}</span>
                </template>
            </Table>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 xl:col-span-3">
            <div class="mb-3 text-base font-semibold">Stats</div>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Total</div>
                    <div class="text-lg font-semibold">{{ stats.total }}</div>
                </div>
                <div class="rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Avg Rating</div>
                    <div class="text-lg font-semibold">{{ stats.averageRating }}</div>
                </div>
                <div class="rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Verified</div>
                    <div class="text-lg font-semibold">{{ stats.verified }}</div>
                </div>
                <div class="rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Helpful</div>
                    <div class="text-lg font-semibold">{{ stats.helpfulCount }}</div>
                </div>
                <div class="rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Approved</div>
                    <div class="text-lg font-semibold">{{ stats.statusApproved }}</div>
                </div>
                <div class="rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Pending</div>
                    <div class="text-lg font-semibold">{{ stats.statusPending }}</div>
                </div>
                <div class="col-span-2 rounded-md border border-gray-200 p-2">
                    <div class="text-gray-500">Rejected</div>
                    <div class="text-lg font-semibold">{{ stats.statusRejected }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
