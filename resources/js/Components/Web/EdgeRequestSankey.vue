<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { useElementSize } from "@vueuse/core"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBan, faCheck, faQuestionCircle, faServer } from "@fal"

library.add(faBan, faCheck, faQuestionCircle, faServer)

type SankeyNode = { id: string; label: string; value: number; color: string; column: number; note?: string; href?: string; outcome?: keyof typeof outcomes }
type SankeyLink = { source: string; target: string; value: number; color?: string }

const props = defineProps<{
    nodes: SankeyNode[]
    links: SankeyLink[]
    total: number
}>()

const locale = useLocaleStore()
const container = ref<HTMLElement | null>(null)
const { width } = useElementSize(container)

const HEIGHT = 200
const TOP = 18
const NODE_WIDTH = 6
const GAP = 16
const MIN_BAND = 3
const LABEL_SPACE = 300

const layout = computed(() => {
    const nodes = props.nodes.filter((node) => node.value > 0)
    const columns = Math.max(0, ...nodes.map((node) => node.column)) + 1
    const byColumn = Array.from({ length: columns }, (_, column) => nodes.filter((node) => node.column === column))
    const scale = Math.min(...byColumn.map((column) => (HEIGHT - GAP * (column.length - 1)) / Math.max(1, column.reduce((total, node) => total + node.value, 0))))
    const usable = Math.max(0, width.value - LABEL_SPACE - NODE_WIDTH)

    const ids = new Set(nodes.map((node) => node.id))
    const links = props.links.filter((link) => link.value > 0 && ids.has(link.source) && ids.has(link.target)).map((link) => ({ ...link, thickness: Math.max(MIN_BAND, link.value * scale) }))
    const flowAt = (id: string, side: "source" | "target") => links.filter((link) => link[side] === id).reduce((total, link) => total + link.thickness, 0)

    const placed = new Map<string, SankeyNode & { x: number; y: number; h: number; above: boolean; outUsed: number; inUsed: number }>()
    let chartHeight = 0
    byColumn.forEach((column, columnIndex) => {
        let y = 0
        column.forEach((node) => {
            const h = Math.max(MIN_BAND, flowAt(node.id, "source"), flowAt(node.id, "target"))
            const above = column.length === 1 && columnIndex < columns - 1
            placed.set(node.id, { ...node, x: columns > 1 ? (usable * columnIndex) / (columns - 1) : 0, y, h, above, outUsed: 0, inUsed: 0 })
            y += h + GAP
        })
        chartHeight = Math.max(chartHeight, y - GAP)
    })
    byColumn[0]?.forEach((node) => {
        const first = placed.get(node.id)!
        first.y = chartHeight - first.h
    })

    const bands = links.map((link) => {
        const source = placed.get(link.source)!
        const target = placed.get(link.target)!
        const sx = source.x + NODE_WIDTH
        const tx = target.x
        const sy0 = source.y + source.outUsed
        const ty0 = target.y + target.inUsed
        source.outUsed += link.thickness
        target.inUsed += link.thickness
        const mx = (sx + tx) / 2
        return {
            key: `${link.source}-${link.target}`,
            source: link.source,
            target: link.target,
            color: link.color ?? target.color,
            href: target.href,
            title: `${source.label} → ${target.label}: ${locale.number(link.value)}`,
            path: `M${sx},${sy0} C${mx},${sy0} ${mx},${ty0} ${tx},${ty0} L${tx},${ty0 + link.thickness} C${mx},${ty0 + link.thickness} ${mx},${sy0 + link.thickness} ${sx},${sy0 + link.thickness} Z`,
        }
    })

    const sources = new Set(links.map((link) => link.source))

    return { nodes: [...placed.values()].map((node) => ({ ...node, final: !sources.has(node.id) })), bands, chartHeight }
})

const share = (value: number) => (props.total && value !== props.total ? `${((value / props.total) * 100).toFixed(1)}%` : "")
const open = (href?: string) => href && router.visit(href)
const hovered = ref<string | null>(null)
const outcomes = {
    hit: { icon: "fal fa-check", note: "fill-green-700", color: "text-green-600" },
    blocked: { icon: "fal fa-ban", note: "fill-red-700", color: "text-red-600" },
    rendered: { icon: "fal fa-server", note: "fill-[#6250d6]", color: "text-[#6250d6]" },
    unknown: { icon: "fal fa-question-circle", note: "fill-gray-400", color: "text-gray-400" },
}
const parts = ref<SVGTextElement[]>([])
const partWidths = ref<Record<string, number>>({})
watch(
    layout,
    async () => {
        await nextTick()
        partWidths.value = Object.fromEntries(parts.value.map((part) => [part.dataset.part, part.getComputedTextLength()]))
    },
    { immediate: true }
)
const columnWidths = computed(() => {
    const widths: Record<number, { label: number; value: number; share: number }> = {}
    layout.value.nodes.filter((node) => !node.above).forEach((node) => {
        const column = (widths[node.column] ??= { label: 0, value: 0, share: 0 })
        column.label = Math.max(column.label, partWidths.value[`${node.id}:label`] ?? 0)
        column.value = Math.max(column.value, partWidths.value[`${node.id}:value`] ?? 0)
        column.share = Math.max(column.share, partWidths.value[`${node.id}:share`] ?? 0)
    })
    return widths
})
const cells = (node: { x: number; column: number }) => {
    const widths = columnWidths.value[node.column] ?? { label: 0, value: 0, share: 0 }
    const label = node.x + NODE_WIDTH + 8
    const value = label + widths.label + 8 + widths.value
    const share = value + 6 + widths.share
    return { label, value, share, outcome: share + 12 }
}
const isActive = (band: { key: string; source: string; target: string }) => hovered.value === band.key || hovered.value === band.source || hovered.value === band.target
</script>

<template>
    <div ref="container" class="w-full">
        <svg v-if="width" :width="width" :height="TOP + layout.chartHeight + 4" class="block overflow-visible text-xs">
            <g :transform="`translate(0,${TOP})`">
                <path
                    v-for="band in layout.bands"
                    :key="band.key"
                    :d="band.path"
                    :fill="band.color"
                    :fill-opacity="isActive(band) ? 0.55 : 0.22"
                    class="transition-[fill-opacity]"
                    :class="band.href ? 'cursor-pointer' : ''"
                    @mouseenter="hovered = band.key"
                    @mouseleave="hovered = null"
                    @click="open(band.href)"
                >
                    <title>{{ band.title }}</title>
                </path>
                <g
                    v-for="node in layout.nodes"
                    :key="node.id"
                    :class="node.href ? 'cursor-pointer' : ''"
                    @mouseenter="hovered = node.id"
                    @mouseleave="hovered = null"
                    @click="open(node.href)"
                >
                    <rect :x="node.x" :y="node.y" :width="NODE_WIDTH" :height="node.h" :fill="node.color" rx="1.5" />
                    <text
                        v-if="node.above"
                        :x="node.x"
                        :y="node.y - 7"
                        :class="[node.final ? 'fill-gray-900' : 'fill-gray-700', node.href ? 'hover:underline' : '']"
                        stroke="white"
                        stroke-width="3"
                        paint-order="stroke"
                        stroke-linejoin="round"
                    >
                        <tspan class="font-medium">{{ node.label }}</tspan>
                        <tspan dx="6" class="tabular-nums">{{ locale.number(node.value) }}</tspan>
                        <tspan v-if="share(node.value)" dx="4" class="tabular-nums fill-gray-500">{{ share(node.value) }}</tspan>
                    </text>
                    <g v-else :class="node.href ? 'hover:underline' : ''" stroke="white" stroke-width="3" paint-order="stroke" stroke-linejoin="round">
                        <text ref="parts" :data-part="`${node.id}:label`" :x="cells(node).label" :y="node.y + node.h / 2" dominant-baseline="middle" class="font-medium" :class="node.final ? 'fill-gray-900' : 'fill-gray-700'">{{ node.label }}</text>
                        <text ref="parts" :data-part="`${node.id}:value`" :x="cells(node).value" :y="node.y + node.h / 2" dominant-baseline="middle" text-anchor="end" class="tabular-nums" :class="node.final ? 'fill-gray-900' : 'fill-gray-700'">{{ locale.number(node.value) }}</text>
                        <text ref="parts" :data-part="`${node.id}:share`" :x="cells(node).share" :y="node.y + node.h / 2" dominant-baseline="middle" text-anchor="end" class="tabular-nums" :class="node.final ? 'fill-gray-900' : 'fill-gray-500'">{{ share(node.value) }}</text>
                    </g>
                    <template v-if="node.outcome && !node.above">
                        <FontAwesomeIcon
                            :icon="outcomes[node.outcome].icon"
                            :class="outcomes[node.outcome].color"
                            :x="cells(node).outcome"
                            :y="node.y + node.h / 2 - 6"
                            width="12"
                            height="12"
                            aria-hidden="true"
                        />
                        <text :x="cells(node).outcome + 17" :y="node.y + node.h / 2" dominant-baseline="middle" :class="outcomes[node.outcome].note">{{ node.note }}</text>
                    </template>
                    <title>{{ node.label }}: {{ locale.number(node.value) }} {{ share(node.value) }}{{ node.note ? ` · ${node.note}` : "" }}</title>
                </g>
            </g>
        </svg>
    </div>
</template>
