<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { forceCenter, forceCollide, forceLink, forceManyBody, forceSimulation, Simulation, SimulationLinkDatum, SimulationNodeDatum } from "d3-force"
import { select } from "d3-selection"
import { zoom, zoomIdentity, ZoomBehavior } from "d3-zoom"
import { drag } from "d3-drag"
import ToggleSwitch from "primevue/toggleswitch"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type GraphNode = SimulationNodeDatum & {
    id: string
    path: string
    depth: number | null
    inlinks: number
    is_indexable: boolean
    has_errors: boolean
}

type GraphLink = SimulationLinkDatum<GraphNode> & { count: number }

type GraphData = {
    nodes: GraphNode[]
    links: Array<{ source: string, target: string, count: number }>
    total_pages: number
    is_truncated: boolean
    has_links: boolean
}

const props = defineProps<{
    graphRoute: routeType
    crawlRoute: routeType
}>()

const locale = useLocaleStore()

const container = ref<HTMLElement | null>(null)
const svgElement = ref<SVGSVGElement | null>(null)
const data = ref<GraphData | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)
const isContentOnly = ref(true)
const hovered = ref<{ node: GraphNode, x: number, y: number } | null>(null)

const toggleClass = "[&.p-toggleswitch-checked_.p-toggleswitch-slider]:!bg-[var(--app-accent)]"

const depthSteps = [
    { label: ctrans("Home"), fill: "var(--app-accent-deep)" },
    { label: ctrans("1 click"), fill: "var(--app-accent)" },
    { label: ctrans("2 clicks"), fill: "color-mix(in srgb, var(--app-accent) 70%, white)" },
    { label: ctrans("3 clicks"), fill: "color-mix(in srgb, var(--app-accent) 48%, white)" },
    { label: ctrans("4+ clicks"), fill: "color-mix(in srgb, var(--app-accent) 30%, white)" },
]

const unreachedFill = "#9ca3af"

const depthFill = (depth: number | null) => depth === null ? unreachedFill : depthSteps[Math.min(depth, depthSteps.length - 1)].fill

const radius = (node: GraphNode) => Math.min(18, 4 + Math.sqrt(node.inlinks) * 1.1)

let simulation: Simulation<GraphNode, GraphLink> | null = null
let zoomBehavior: ZoomBehavior<SVGSVGElement, unknown> | null = null

const topPages = computed(() => [...(data.value?.nodes ?? [])].sort((a, b) => b.inlinks - a.inlinks).slice(0, 15))

const load = async () => {
    isLoading.value = true
    error.value = null

    try {
        const response = await axios.get(route(props.graphRoute.name, props.graphRoute.parameters), { params: { content_only: isContentOnly.value ? 1 : 0 } })
        data.value = response.data
    } catch {
        error.value = ctrans("The link graph could not be loaded. Reload the page to try again.")
    } finally {
        isLoading.value = false
    }
}

const openPage = (node: GraphNode) => router.visit(route(props.crawlRoute.name, { ...(props.crawlRoute.parameters as object), tab: "internal", search: node.path }))

const draw = () => {
    simulation?.stop()

    if (!svgElement.value || !container.value || !data.value || !data.value.nodes.length) {
        return
    }

    const width = container.value.clientWidth
    const height = container.value.clientHeight
    const nodes: GraphNode[] = data.value.nodes.map((node) => ({ ...node }))
    const links: GraphLink[] = data.value.links.map((link) => ({ ...link }))
    const neighbours = new Map<string, Set<string>>()

    for (const link of data.value.links) {
        neighbours.set(link.source, (neighbours.get(link.source) ?? new Set()).add(link.target))
        neighbours.set(link.target, (neighbours.get(link.target) ?? new Set()).add(link.source))
    }

    const svg = select(svgElement.value)
    svg.selectAll("*").remove()
    svg.attr("viewBox", `0 0 ${width} ${height}`)

    const scene = svg.append("g")

    zoomBehavior = zoom<SVGSVGElement, unknown>().scaleExtent([0.2, 6]).on("zoom", (event) => scene.attr("transform", event.transform))
    svg.call(zoomBehavior).on("dblclick.zoom", null)

    const linkSelection = scene.append("g")
        .attr("stroke", "#d1d5db")
        .attr("stroke-opacity", 0.7)
        .selectAll("line")
        .data(links)
        .join("line")
        .attr("stroke-width", (link) => Math.min(3, 0.75 + Math.log2(link.count)))

    const nodeSelection = scene.append("g")
        .selectAll<SVGCircleElement, GraphNode>("circle")
        .data(nodes)
        .join("circle")
        .attr("r", radius)
        .attr("fill", (node) => depthFill(node.depth))
        .attr("stroke", (node) => node.has_errors ? "#dc2626" : "#ffffff")
        .attr("stroke-width", (node) => node.has_errors ? 2.5 : 1.5)
        .style("cursor", "pointer")
        .on("mouseenter", (event: MouseEvent, node) => {
            const box = container.value?.getBoundingClientRect()
            hovered.value = { node, x: event.clientX - (box?.left ?? 0), y: event.clientY - (box?.top ?? 0) }
            const related = neighbours.get(node.id) ?? new Set()
            nodeSelection.attr("opacity", (other) => other.id === node.id || related.has(other.id) ? 1 : 0.2)
            linkSelection.attr("stroke", (link) => (link.source as GraphNode).id === node.id || (link.target as GraphNode).id === node.id ? "#6b7280" : "#e5e7eb")
        })
        .on("mousemove", (event: MouseEvent) => {
            const box = container.value?.getBoundingClientRect()

            if (hovered.value) {
                hovered.value = { ...hovered.value, x: event.clientX - (box?.left ?? 0), y: event.clientY - (box?.top ?? 0) }
            }
        })
        .on("mouseleave", () => {
            hovered.value = null
            nodeSelection.attr("opacity", 1)
            linkSelection.attr("stroke", "#d1d5db")
        })
        .on("click", (_event, node) => openPage(node))

    nodeSelection.call(drag<SVGCircleElement, GraphNode>()
        .on("start", (event, node) => {
            if (!event.active) {
                simulation?.alphaTarget(0.3).restart()
            }
            node.fx = node.x
            node.fy = node.y
        })
        .on("drag", (event, node) => {
            node.fx = event.x
            node.fy = event.y
        })
        .on("end", (event, node) => {
            if (!event.active) {
                simulation?.alphaTarget(0)
            }
            node.fx = null
            node.fy = null
        }))

    simulation = forceSimulation<GraphNode, GraphLink>(nodes)
        .force("link", forceLink<GraphNode, GraphLink>(links).id((node) => node.id).distance(46).strength(0.4))
        .force("charge", forceManyBody().strength(-90))
        .force("center", forceCenter(width / 2, height / 2))
        .force("collide", forceCollide<GraphNode>().radius((node) => radius(node) + 2))
        .on("tick", () => {
            linkSelection
                .attr("x1", (link) => (link.source as GraphNode).x ?? 0)
                .attr("y1", (link) => (link.source as GraphNode).y ?? 0)
                .attr("x2", (link) => (link.target as GraphNode).x ?? 0)
                .attr("y2", (link) => (link.target as GraphNode).y ?? 0)
            nodeSelection.attr("cx", (node) => node.x ?? 0).attr("cy", (node) => node.y ?? 0)
        })
        .on("end", () => fitToView(nodes, width, height))
}

const FIT_PADDING = 40

const MAX_FIT_SCALE = 1.5

const fitToView = (nodes: GraphNode[], width: number, height: number) => {
    if (!svgElement.value || !zoomBehavior || !nodes.length) {
        return
    }

    const xs = nodes.map((node) => node.x ?? 0)
    const ys = nodes.map((node) => node.y ?? 0)
    const [minX, maxX, minY, maxY] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)]
    const scale = Math.min(MAX_FIT_SCALE, (width - FIT_PADDING * 2) / Math.max(1, maxX - minX), (height - FIT_PADDING * 2) / Math.max(1, maxY - minY))
    const transform = zoomIdentity.translate(width / 2, height / 2).scale(scale).translate(-(minX + maxX) / 2, -(minY + maxY) / 2)

    select(svgElement.value).call(zoomBehavior.transform, transform)
    fittedTransform = transform
}

let fittedTransform = zoomIdentity

const resetZoom = () => {
    if (svgElement.value && zoomBehavior) {
        select(svgElement.value).call(zoomBehavior.transform, fittedTransform)
    }
}

watch(data, draw)
watch(isContentOnly, load)

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
    load()

    if (container.value) {
        let lastWidth = container.value.clientWidth
        resizeObserver = new ResizeObserver(() => {
            if (container.value && Math.abs(container.value.clientWidth - lastWidth) > 40) {
                lastWidth = container.value.clientWidth
                draw()
            }
        })
        resizeObserver.observe(container.value)
    }
})

onBeforeUnmount(() => {
    simulation?.stop()
    resizeObserver?.disconnect()
})
</script>

<template>
    <section class="space-y-3" aria-labelledby="link-graph-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="link-graph-title" class="text-base font-semibold text-gray-900">{{ ctrans("Internal links between the most linked pages") }}</h2>
                <p class="text-xs text-gray-600">
                    <template v-if="data?.is_truncated">{{ ctrans("The :shown most linked of :total pages.", { shown: locale.number(data.nodes.length), total: locale.number(data.total_pages) }) }} </template>
                    {{ ctrans("Drag to move, scroll to zoom, click a page to see it in the crawl.") }}
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs text-gray-700">
                <label class="flex cursor-pointer items-center gap-2">
                    <ToggleSwitch v-model="isContentOnly" :class="toggleClass" />
                    {{ ctrans("Content links only") }}
                </label>
                <button type="button" class="cursor-pointer rounded px-2 py-1 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--app-accent)]" @click="resetZoom">{{ ctrans("Reset view") }}</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div ref="container" class="relative h-[34rem] overflow-hidden rounded-md border border-gray-200 bg-white">
                <svg ref="svgElement" class="h-full w-full" role="img" :aria-label="ctrans('Force graph of the internal links. The table beside it lists the most linked pages.')" />

                <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/70"><LoadingIcon /></div>
                <p v-else-if="error" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-red-600">{{ error }}</p>
                <p v-else-if="data && !data.has_links" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-gray-600">{{ ctrans("This crawl ran before links were recorded. Start a new crawl to see its link graph.") }}</p>
                <p v-else-if="data && !data.nodes.length" class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-gray-600">{{ ctrans("This crawl has no page that answered 200.") }}</p>

                <div
                    v-if="hovered"
                    class="pointer-events-none absolute z-10 max-w-xs rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs shadow-md"
                    :style="{ left: `${Math.min(hovered.x + 12, (container?.clientWidth ?? 400) - 220)}px`, top: `${hovered.y + 12}px` }"
                >
                    <p class="break-all font-medium text-gray-900">{{ hovered.node.path }}</p>
                    <p class="text-gray-700">{{ ctrans(":count inlinks", { count: locale.number(hovered.node.inlinks) }) }} · {{ hovered.node.depth === null ? ctrans("not reached by links") : hovered.node.depth === 0 ? ctrans("home page") : ctrans(":depth clicks deep", { depth: hovered.node.depth }) }}</p>
                    <p v-if="hovered.node.has_errors" class="text-red-700">{{ ctrans("Has errors") }}</p>
                    <p v-if="!hovered.node.is_indexable" class="text-amber-800">{{ ctrans("Non-indexable") }}</p>
                </div>

                <div class="absolute bottom-2 left-2 flex flex-wrap items-center gap-x-3 gap-y-1 rounded bg-white/90 px-2 py-1 text-[11px] text-gray-700">
                    <span v-for="step in depthSteps" :key="step.label" class="flex items-center gap-1">
                        <span class="h-2.5 w-2.5 rounded-full border border-white" :style="{ background: step.fill }" aria-hidden="true" />{{ step.label }}
                    </span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full" :style="{ background: unreachedFill }" aria-hidden="true" />{{ ctrans("Not reached") }}</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full border-2 border-red-600 bg-white" aria-hidden="true" />{{ ctrans("Has errors") }}</span>
                </div>
            </div>

            <div class="min-w-0 rounded-md border border-gray-200 bg-white">
                <h3 class="border-b border-gray-100 px-3 py-2 text-xs font-semibold text-gray-900">{{ ctrans("Most linked pages") }}</h3>
                <table class="w-full text-xs">
                    <thead class="sr-only">
                        <tr><th scope="col">{{ ctrans("Page") }}</th><th scope="col">{{ ctrans("Inlinks") }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="node in topPages" :key="node.id">
                            <td class="max-w-0 px-3 py-1.5">
                                <button type="button" class="block w-full cursor-pointer truncate text-left text-gray-900 hover:text-[var(--app-accent)] hover:underline focus-visible:underline focus-visible:outline-none" :title="node.path" @click="openPage(node)">{{ node.path }}</button>
                            </td>
                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-700">{{ locale.number(node.inlinks) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
