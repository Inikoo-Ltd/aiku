<script setup lang="ts">
import Tag from "primevue/tag"
import { ctrans } from "@/Composables/useTrans"

type CapacityRow = {
    warehouse_id: number
    code: string
    name: string
    stream: "dropshipping" | "wholesale"
    status: "ok" | "at_capacity" | "over" | "no_data"
    in_per_day: number
    out_per_day: number
    gap_per_day: number
    median_hours: number | null
    backlog: number
    late_backlog: number
    late_to_pick: number
    late_to_pack: number
    pickers: number
    packers: number
    picks_per_picker_day: number | null
    packs_per_packer_day: number | null
    bottleneck: "picking" | "packing"
    extra_picks_per_day: number
    extra_packs_per_day: number
    extra_pickers: number | null
    extra_packers: number | null
    backlog_clearing_days: number
}

defineProps<{
    rows: CapacityRow[]
    currentWarehouseId?: number
}>()

const statusLabel = {
    ok: ctrans("OK"),
    at_capacity: ctrans("At capacity"),
    over: ctrans("Over capacity"),
    no_data: ctrans("Nothing dispatched"),
}

const statusClass = {
    ok: "!bg-green-100 !text-green-700",
    at_capacity: "!bg-amber-100 !text-amber-800",
    over: "!bg-red-100 !text-red-700",
    no_data: "!bg-gray-100 !text-gray-600",
}

const streamLabel = {
    dropshipping: ctrans("Dropshipping"),
    wholesale: ctrans("Wholesale, partners and marketplaces"),
}

const signed = (value: number) => (value > 0 ? "+" : "") + value
</script>

<template>
    <section class="mx-4 mt-6 mb-4 rounded-lg border border-gray-200 dark:border-gray-700">
        <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-4 py-2 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ ctrans("Warehouse capacity") }}</h3>
            <span class="text-xs text-gray-500">{{ ctrans("Orders and replacements, last 14 days, per working day. Fulfilment returns are not counted.") }}</span>
        </header>

        <div v-if="!rows.length" class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No orders in the last 14 days") }}</div>

        <div v-else class="grid gap-3 p-3 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="row in rows"
                :key="row.warehouse_id + row.stream"
                class="rounded-md border px-3 py-2"
                :class="row.warehouse_id === currentWarehouseId ? 'border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)]' : 'border-gray-200 dark:border-gray-700'"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="truncate text-sm">
                        <span class="font-semibold whitespace-nowrap">{{ row.code }}</span>
                        <span class="ml-1 font-medium">{{ streamLabel[row.stream] }}</span>
                    </div>
                    <Tag :value="statusLabel[row.status]" :pt="{ root: { class: ['shrink-0 !text-xs !font-medium', statusClass[row.status]] } }" />
                </div>

                <dl class="mt-2 grid grid-cols-4 gap-2 text-xs">
                    <div>
                        <dt class="whitespace-nowrap text-gray-500">{{ ctrans("Median") }}</dt>
                        <dd class="font-semibold tabular-nums">{{ row.median_hours === null ? "—" : ctrans(":hours h", { hours: Math.round(row.median_hours) }) }}</dd>
                    </div>
                    <div>
                        <dt class="whitespace-nowrap text-gray-500">{{ ctrans("In / out") }}</dt>
                        <dd class="font-semibold tabular-nums whitespace-nowrap">{{ Math.round(row.in_per_day) }} / {{ Math.round(row.out_per_day) }}</dd>
                    </div>
                    <div>
                        <dt class="whitespace-nowrap text-gray-500">{{ ctrans("Gap") }}</dt>
                        <dd class="font-semibold tabular-nums">{{ signed(Math.round(row.gap_per_day)) }}</dd>
                    </div>
                    <div v-tooltip="ctrans('Not dispatched. Late: older than one working day, :pick waiting to be picked, :pack waiting to be packed', { pick: row.late_to_pick, pack: row.late_to_pack })">
                        <dt class="whitespace-nowrap text-gray-500">{{ ctrans("Backlog") }}</dt>
                        <dd class="font-semibold tabular-nums whitespace-nowrap">{{ row.backlog }} <span class="font-normal text-gray-500">({{ ctrans(":late late", { late: row.late_backlog }) }})</span></dd>
                    </div>
                </dl>

                <div v-if="row.status === 'over' || row.status === 'at_capacity'" class="mt-2 border-t border-gray-100 pt-2 text-xs dark:border-gray-800">
                    <div class="text-gray-600 dark:text-gray-300">
                        {{ row.bottleneck === "picking"
                            ? ctrans("Late orders are waiting for pickers: :pick to pick, :pack to pack.", { pick: row.late_to_pick, pack: row.late_to_pack })
                            : ctrans("Late orders are waiting for packers: :pack to pack, :pick to pick.", { pick: row.late_to_pick, pack: row.late_to_pack }) }}
                    </div>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        <div v-for="role in (row.bottleneck === 'picking' ? ['picking', 'packing'] : ['packing', 'picking'])" :key="role">
                            <template v-if="role === 'picking'">
                                <div class="text-sm font-semibold whitespace-nowrap">
                                    {{ row.extra_pickers === null ? ctrans(":orders more picks a day", { orders: row.extra_picks_per_day }) : ctrans("+:workers pickers", { workers: row.extra_pickers }) }}
                                </div>
                                <div class="text-gray-500">
                                    {{ row.picks_per_picker_day === null
                                        ? ctrans("Too few picking records to count pickers")
                                        : ctrans(":pickers pickers now, :output orders each a day", { pickers: row.pickers, output: Math.round(row.picks_per_picker_day) }) }}
                                </div>
                            </template>
                            <template v-else>
                                <div class="text-sm font-semibold whitespace-nowrap">
                                    {{ row.extra_packers === null ? ctrans(":orders more packs a day", { orders: row.extra_packs_per_day }) : ctrans("+:workers packers", { workers: row.extra_packers }) }}
                                </div>
                                <div class="text-gray-500">
                                    {{ row.packs_per_packer_day === null
                                        ? ctrans("Too few packing records to count packers")
                                        : ctrans(":packers packers now, :output orders each a day", { packers: row.packers, output: Math.round(row.packs_per_packer_day) }) }}
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="mt-1 text-gray-400">
                        {{ ctrans("To keep up with new orders and clear the late ones in :days working days.", { days: row.backlog_clearing_days }) }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
