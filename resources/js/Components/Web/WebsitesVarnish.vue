<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router, usePoll } from "@inertiajs/vue3"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import EdgeTrafficChart from "@/Components/Web/EdgeTrafficChart.vue"
import { ctrans } from "@/Composables/useTrans"

type Series = (number | null)[]

const props = defineProps<{
    data: {
        range: string
        ranges: string[]
        buckets: string[]
        hosts: Record<string, { hits: Series; misses: Series; memory_mb: Series }>
        page_views: Series
        releases: { index: number; label: string }[]
    }
    tab?: string
}>()

const loading = ref(false)

const poll = usePoll(60000, { only: ["varnish"] }, { autoStart: false })

const range = computed({
    get: () => props.data.range,
    set: (value: string) => {
        loading.value = true
        router.reload({ only: ["varnish"], data: { tab: props.tab ?? "varnish", varnish_range: value }, onFinish: () => (loading.value = false) })
    },
})
const rangeOptions = computed(() => props.data.ranges.map((value) => ({ label: value.toUpperCase(), value })))

const hostColors = ["#7c3aed", "#0ea5e9", "#f59e0b", "#10b981"]
const hosts = computed(() => Object.entries(props.data.hosts).map(([slug, series], index) => ({ slug, ...series, color: hostColors[index % hostColors.length] })))

const isShortRange = computed(() => ["1h", "6h", "24h"].includes(props.data.range))
watch(isShortRange, (short) => (short ? poll.start() : poll.stop()), { immediate: true })
const labels = computed(() => props.data.buckets.map((iso) => new Date(iso).toLocaleString(undefined, isShortRange.value
    ? { hour: "numeric", minute: "2-digit" }
    : { day: "numeric", month: "short", hour: "numeric" })))

const add = (a: number | null, b: number | null) => (a === null ? b : b === null ? a : a + b)
const total = (pick: (host: (typeof hosts.value)[number]) => Series): Series =>
    props.data.buckets.map((_, index) => hosts.value.reduce<number | null>((sum, host) => add(sum, pick(host)[index]), null))
const hitRatio = (hits: Series, misses: Series): Series =>
    hits.map((hit, index) => (hit === null || misses[index] === null || hit + (misses[index] as number) === 0 ? null : (hit / (hit + (misses[index] as number))) * 100))

const hitRatioDatasets = computed(() => [
    ...hosts.value.map((host) => ({ label: host.slug, data: hitRatio(host.hits, host.misses), color: host.color })),
    ...(hosts.value.length > 1 ? [{ label: ctrans("All"), data: hitRatio(total((host) => host.hits), total((host) => host.misses)), color: "#9ca3af" }] : []),
])
const memoryDatasets = computed(() => hosts.value.map((host) => ({ label: host.slug, data: host.memory_mb, color: host.color })))
const pageViewDatasets = computed(() => [{ label: ctrans("Page views"), data: props.data.page_views, color: "#7c3aed" }])
const hitsDatasets = computed(() => {
    const hits = total((host) => host.hits)
    const misses = total((host) => host.misses)
    return [
        { label: ctrans("Requests"), data: hits.map((hit, index) => add(hit, misses[index])), color: "#7c3aed" },
        { label: ctrans("Misses"), data: misses, color: "#312e81" },
    ]
})

const releaseIndexes = computed(() => props.data.releases.map((release) => release.index))
const releasesCaption = computed(() => (props.data.releases.length
    ? ctrans("Dashed lines: :count deploys, last :version", { count: props.data.releases.length, version: props.data.releases[props.data.releases.length - 1].label })
    : ""))

const cards = computed(() => [
    { title: ctrans("Hit ratio"), help: ctrans("Share of Varnish lookups answered from its cache (hits ÷ hits + misses), per box."), datasets: hitRatioDatasets.value, unit: "percent" as const },
    { title: ctrans("Memory usage (MB)"), help: ctrans("Memory each Varnish holds for cached objects."), datasets: memoryDatasets.value, unit: "count" as const },
    { title: ctrans("Real website hits"), help: ctrans("Storefront page views per minute reported by browsers running the page script, bots excluded."), datasets: pageViewDatasets.value, unit: "count" as const },
    { title: ctrans("Hits"), help: ctrans("Varnish requests (hits + misses) and misses per minute, boro and litio together."), datasets: hitsDatasets.value, unit: "count" as const },
])
</script>

<template>
    <div class="px-4 py-4 space-y-4 text-sm text-gray-700">
        <div class="flex flex-wrap items-center gap-3">
            <SegmentedToggle v-model="range" :options="rangeOptions" :ariaLabel="ctrans('Range')" />
            <span v-if="isShortRange" class="text-xs text-gray-500">{{ ctrans("Updates every minute") }}</span>
        </div>

        <div v-if="!hosts.length" class="rounded-md border border-gray-200 bg-white px-4 py-3 text-xs text-gray-500">
            {{ ctrans("No Varnish samples in this range yet. They come from the server metrics sampler on each web box, every minute.") }}
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3 transition-opacity" :class="{ 'opacity-50 pointer-events-none': loading }">
            <div v-for="card in cards" :key="card.title" class="rounded-md border border-gray-200 bg-white px-4 py-3">
                <div v-tooltip="card.help" class="font-medium text-gray-700 whitespace-nowrap">{{ card.title }}</div>
                <EdgeTrafficChart class="mt-3" type="line" :labels="labels" :datasets="card.datasets" :unit="card.unit" :eventIndexes="releaseIndexes" />
                <div v-if="releasesCaption" class="mt-1 text-xs text-gray-500">{{ releasesCaption }}</div>
            </div>
        </div>
    </div>
</template>
