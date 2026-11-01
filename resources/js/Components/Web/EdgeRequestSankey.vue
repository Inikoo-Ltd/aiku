<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useElementSize } from "@vueuse/core"
import { useLocaleStore } from "@/Stores/locale"

type SankeyNode = { id: string; label: string; value: number; color: string; column: number; note?: string; href?: string }
type SankeyLink = { source: string; target: string; value: number; color?: string }

const props = defineProps<{
    nodes: SankeyNode[]
    links: SankeyLink[]
    total: number
}>()

const locale = useLocaleStore()
const container = ref<HTMLElement | null>(null)
const { width } = useElementSize(container)

const HEIGHT = 120
const TOP = 18
const NODE_WIDTH = 6
const GAP = 12
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

    return { nodes: [...placed.values()], bands, chartHeight }
})

const share = (value: number) => (props.total && value !== props.total ? `${((value / props.total) * 100).toFixed(1)}%` : "")
const open = (href?: string) => href && router.visit(href)
const hovered = ref<string | null>(null)
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
                        :x="node.above ? node.x : node.x + NODE_WIDTH + 8"
                        :y="node.above ? node.y - 7 : node.y + node.h / 2"
                        :dominant-baseline="node.above ? 'auto' : 'middle'"
                        class="fill-gray-700"
                        :class="node.href ? 'hover:underline' : ''"
                        stroke="white"
                        stroke-width="3"
                        paint-order="stroke"
                        stroke-linejoin="round"
                    >
                        <tspan class="font-medium">{{ node.label }}</tspan>
                        <tspan dx="6" class="tabular-nums">{{ locale.number(node.value) }}</tspan>
                        <tspan v-if="share(node.value)" dx="4" class="fill-gray-500 tabular-nums">{{ share(node.value) }}</tspan>
                        <tspan v-if="node.note" dx="6" class="fill-gray-400">{{ node.note }}</tspan>
                    </text>
                    <title>{{ node.label }}: {{ locale.number(node.value) }} {{ share(node.value) }}</title>
                </g>
            </g>
        </svg>
    </div>
</template>
