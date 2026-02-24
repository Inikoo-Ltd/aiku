<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue"
import OrgNode from "./OrgNode.vue"

interface OrgNodeData {
	id: string
	name: string
	title: string
	avatarUrl?: string
	department?: string
	reports?: OrgNodeData[]
}

interface NodeDragStartPayload {
	node: OrgNodeData
	clientX: number
	clientY: number
}

const props = defineProps<{
	nodes?: OrgNodeData[]
}>()

const emit = defineEmits<{
	nodeClick: [node: OrgNodeData]
}>()

const chartContainer = ref<HTMLElement | null>(null)
const svgElement = ref<SVGSVGElement | null>(null)
const transform = ref({ x: 0, y: 0, scale: 1 })
const isDragging = ref(false)
const panStartPointer = ref({ x: 0, y: 0 })
const panStartTransform = ref({ x: 0, y: 0 })
const focusedNodeId = ref<string | null>(null)
const expandedNodes = ref<Set<string>>(new Set())
const svgWidth = ref(1200)
const svgHeight = ref(800)
const nodeOffsets = ref<Map<string, { x: number; y: number }>>(new Map())
const activeNodeDrag = ref<{
	nodeId: string
	startClientX: number
	startClientY: number
	originX: number
	originY: number
	moved: boolean
} | null>(null)
const justDraggedNodeId = ref<string | null>(null)

const displayNodes = computed(() => {
	return props.nodes ?? []
})

const getInitialExpandedNodes = (nodes: OrgNodeData[], maxDepth: number): Set<string> => {
	const expandedIds = new Set<string>()

	const walk = (entries: OrgNodeData[], depth: number) => {
		for (const entry of entries) {
			const children = entry.reports || []
			if (children.length === 0) {
				continue
			}

			if (depth <= maxDepth) {
				expandedIds.add(entry.id)
			}

			if (depth < maxDepth) {
				walk(children, depth + 1)
			}
		}
	}

	walk(nodes, 0)

	return expandedIds
}

const nodeWidth = 200
const nodeHeight = 100
const horizontalGap = 40
const verticalGap = 80
const minScale = 0.2
const maxScale = Number.POSITIVE_INFINITY
const zoomInStep = 1.35
const zoomOutStep = 0.75
const panSpeed = 5
const enableNodeToggle = false
const defaultScale = 1.8
const searchQuery = ref("")
const searchResultIndex = ref(0)
let focusAnimationFrame: number | null = null
const minFocusAnimationDuration = 280
const maxFocusAnimationDuration = 700
const motionBlurPx = ref(0)
let resizeObserver: ResizeObserver | null = null
let centerChartTimer: ReturnType<typeof setTimeout> | null = null

interface LayoutNode {
	node: OrgNodeData
	x: number
	y: number
	children: LayoutNode[]
}

const getNodeOffset = (nodeId: string): { x: number; y: number } => {
	return nodeOffsets.value.get(nodeId) || { x: 0, y: 0 }
}

const calculateLayout = (
	nodes: OrgNodeData[],
	x: number,
	y: number,
	depth: number
): LayoutNode[] => {
	if (!nodes || nodes.length === 0) return []

	const result: LayoutNode[] = []

	if (nodes.length === 1) {
		const node = nodes[0]
		const children = expandedNodes.value.has(node.id)
			? calculateLayout(node.reports || [], x, y + nodeHeight + verticalGap, depth + 1)
			: []
		result.push({ node, x, y, children })
	} else {
		let currentX = x
		const childArrays: LayoutNode[][] = []

		for (const node of nodes) {
			const childLayout = expandedNodes.value.has(node.id)
				? calculateLayout(
						node.reports || [],
						currentX,
						y + nodeHeight + verticalGap,
						depth + 1
					)
				: []
			childArrays.push(childLayout)

			const subtreeWidth = calculateSubtreeWidth(childLayout)
			currentX += subtreeWidth + horizontalGap
		}

		currentX = x
		for (let i = 0; i < nodes.length; i++) {
			const node = nodes[i]
			const subtreeWidth = calculateSubtreeWidth(childArrays[i])
			const nodeX = currentX + (subtreeWidth - nodeWidth) / 2

			result.push({
				node,
				x: nodeX,
				y,
				children: childArrays[i],
			})

			currentX += subtreeWidth + horizontalGap
		}
	}

	return result
}

const calculateSubtreeWidth = (layoutNodes: LayoutNode[]): number => {
	if (!layoutNodes || layoutNodes.length === 0) return nodeWidth
	if (layoutNodes.length === 1) {
		const childWidth = calculateSubtreeWidth(layoutNodes[0].children)
		return Math.max(nodeWidth, childWidth)
	}

	let width = 0
	for (const ln of layoutNodes) {
		width += calculateSubtreeWidth(ln.children)
	}
	width += (layoutNodes.length - 1) * horizontalGap
	return Math.max(width, nodeWidth * layoutNodes.length)
}

const renderTree = (
	layoutNodes: LayoutNode[]
): { nodes: LayoutNode[]; lines: { x1: number; y1: number; x2: number; y2: number }[] } => {
	const lines: { x1: number; y1: number; x2: number; y2: number }[] = []
	const nodes: LayoutNode[] = []

	const traverse = (ln: LayoutNode) => {
		const currentOffset = getNodeOffset(ln.node.id)
		nodes.push({
			...ln,
			x: ln.x + currentOffset.x,
			y: ln.y + currentOffset.y,
		})

		if (ln.children && ln.children.length > 0) {
			const parentX = ln.x + currentOffset.x + nodeWidth / 2
			const parentY = ln.y + currentOffset.y + nodeHeight

			for (const child of ln.children) {
				const childOffset = getNodeOffset(child.node.id)
				const childX = child.x + childOffset.x + nodeWidth / 2
				const childY = child.y + childOffset.y

				lines.push({
					x1: parentX,
					y1: parentY,
					x2: parentX,
					y2: parentY + verticalGap / 2,
				})
				lines.push({
					x1: parentX,
					y1: parentY + verticalGap / 2,
					x2: childX,
					y2: parentY + verticalGap / 2,
				})
				lines.push({
					x1: childX,
					y1: parentY + verticalGap / 2,
					x2: childX,
					y2: childY,
				})

				traverse(child)
			}
		}
	}

	for (const ln of layoutNodes) {
		traverse(ln)
	}

	return { nodes, lines }
}

const layout = computed(() => {
	const tempLayout = calculateLayout(displayNodes.value, 0, 40, 0)
	const totalTreeWidth = calculateSubtreeWidth(tempLayout)
	const startX = (Math.max(svgWidth.value, totalTreeWidth) - nodeWidth) / 2
	const treeLayout = calculateLayout(displayNodes.value, startX, 40, 0)

	// Update SVG dimensions to fit content
	const maxX = calculateMaxX(treeLayout, 0)
	const maxY = calculateMaxY(treeLayout, 0)
	svgWidth.value = Math.max(chartContainer.value?.clientWidth || 1200, maxX + nodeWidth + 100)
	svgHeight.value = Math.max(chartContainer.value?.clientHeight || 800, maxY + nodeHeight + 100)

	return renderTree(treeLayout)
})

const calculateMaxX = (layoutNodes: LayoutNode[], maxX: number): number => {
	for (const ln of layoutNodes) {
		maxX = Math.max(maxX, ln.x)
		if (ln.children.length > 0) {
			maxX = calculateMaxX(ln.children, maxX)
		}
	}
	return maxX
}

const calculateMaxY = (layoutNodes: LayoutNode[], maxY: number): number => {
	for (const ln of layoutNodes) {
		maxY = Math.max(maxY, ln.y)
		if (ln.children.length > 0) {
			maxY = calculateMaxY(ln.children, maxY)
		}
	}
	return maxY
}

const visibleFlatNodes = computed(() => {
	return layout.value.nodes.map((layoutNode) => layoutNode.node)
})

const searchResultIds = computed(() => {
	const keyword = searchQuery.value.trim().toLowerCase()
	if (!keyword) {
		return []
	}

	return visibleFlatNodes.value
		.filter((node) => {
			return [node.name, node.title, node.department]
				.filter(Boolean)
				.join(" ")
				.toLowerCase()
				.includes(keyword)
		})
		.map((node) => node.id)
})

watch(
	displayNodes,
	(nodes) => {
		if (focusAnimationFrame !== null) {
			cancelAnimationFrame(focusAnimationFrame)
			focusAnimationFrame = null
		}

		motionBlurPx.value = 0
		isDragging.value = false
		activeNodeDrag.value = null
		justDraggedNodeId.value = null
		nodeOffsets.value = new Map()

		expandedNodes.value = getInitialExpandedNodes(
			nodes,
			enableNodeToggle ? 1 : Number.MAX_SAFE_INTEGER
		)
		focusedNodeId.value = nodes[0]?.id || null

		requestAnimationFrame(() => {
			scheduleCenterChart()
		})
	},
	{ immediate: true }
)

watch(visibleFlatNodes, (nodes) => {
	if (nodes.length === 0) {
		focusedNodeId.value = null
		return
	}

	const focusedId = focusedNodeId.value
	if (focusedId && nodes.some((node) => node.id === focusedId)) {
		return
	}

	focusedNodeId.value = nodes[0].id
})

watch(searchQuery, () => {
	searchResultIndex.value = 0
})

watch(searchResultIds, (resultIds) => {
	if (resultIds.length === 0) {
		searchResultIndex.value = 0
		return
	}

	if (searchResultIndex.value >= resultIds.length) {
		searchResultIndex.value = 0
	}
})

onMounted(() => {
	if (chartContainer.value) {
		svgWidth.value = Math.max(chartContainer.value.clientWidth, 1200)
		svgHeight.value = Math.max(chartContainer.value.clientHeight, 800)
		resizeObserver = new ResizeObserver(() => {
			scheduleCenterChart()
		})
		resizeObserver.observe(chartContainer.value)
	}
	window.addEventListener("keydown", handleKeydown)

	scheduleCenterChart(100)
})

onUnmounted(() => {
	window.removeEventListener("keydown", handleKeydown)
	if (resizeObserver && chartContainer.value) {
		resizeObserver.unobserve(chartContainer.value)
		resizeObserver.disconnect()
		resizeObserver = null
	}
	if (centerChartTimer !== null) {
		clearTimeout(centerChartTimer)
		centerChartTimer = null
	}
	if (focusAnimationFrame !== null) {
		cancelAnimationFrame(focusAnimationFrame)
		focusAnimationFrame = null
	}
	motionBlurPx.value = 0
})

const scheduleCenterChart = (delay: number = 0) => {
	if (centerChartTimer !== null) {
		clearTimeout(centerChartTimer)
	}

	centerChartTimer = setTimeout(() => {
		centerChartTimer = null
		const didCenter = centerChart()
		if (!didCenter) {
			scheduleCenterChart(120)
		}
	}, delay)
}

const centerChart = (): boolean => {
	if (!chartContainer.value) {
		return false
	}

	const containerWidth = chartContainer.value.clientWidth
	const containerHeight = chartContainer.value.clientHeight
	if (containerWidth <= 0 || containerHeight <= 0) {
		return false
	}

	const bounds = getLayoutBounds()
	const boundsWidth = Math.max(1, bounds.maxX - bounds.minX)
	const boundsHeight = Math.max(1, bounds.maxY - bounds.minY)
	const fitScale = Math.min(containerWidth / boundsWidth, containerHeight / boundsHeight)
	const autoScale = Math.max(minScale, fitScale * 1.4)
	const scale = Math.min(Math.max(Math.max(defaultScale, autoScale), minScale), maxScale)
	const centerX = bounds.minX + boundsWidth / 2
	const centerY = bounds.minY + boundsHeight / 2

	transform.value.scale = scale
	transform.value.x = containerWidth / 2 - centerX * scale
	transform.value.y = containerHeight / 2 - centerY * scale

	return true
}

// Re-center when nodes are expanded/collapsed
watch(
	expandedNodes,
	() => {
		scheduleCenterChart(50)
	},
	{ deep: true }
)

const findNodeById = (nodeId: string, nodes: OrgNodeData[]): OrgNodeData | null => {
	for (const node of nodes) {
		if (node.id === nodeId) {
			return node
		}

		const children = node.reports || []
		if (children.length === 0) {
			continue
		}

		const found = findNodeById(nodeId, children)
		if (found) {
			return found
		}
	}

	return null
}

const handleMouseDown = (e: MouseEvent) => {
	if (e.button !== 0) return
	e.preventDefault()
	isDragging.value = true
	panStartPointer.value = { x: e.clientX, y: e.clientY }
	panStartTransform.value = { x: transform.value.x, y: transform.value.y }
}

const handleMouseMove = (e: MouseEvent) => {
	if (activeNodeDrag.value) {
		const deltaX = e.clientX - activeNodeDrag.value.startClientX
		const deltaY = e.clientY - activeNodeDrag.value.startClientY
		const hasMoved = Math.abs(deltaX) > 2 || Math.abs(deltaY) > 2

		if (hasMoved) {
			activeNodeDrag.value.moved = true
		}

		nodeOffsets.value.set(activeNodeDrag.value.nodeId, {
			x: activeNodeDrag.value.originX + deltaX,
			y: activeNodeDrag.value.originY + deltaY,
		})
		nodeOffsets.value = new Map(nodeOffsets.value)
		return
	}

	if (!isDragging.value) return
	const speed = panSpeed * Math.max(1, Math.sqrt(transform.value.scale))
	const deltaX = (e.clientX - panStartPointer.value.x) * speed
	const deltaY = (e.clientY - panStartPointer.value.y) * speed
	transform.value.x = panStartTransform.value.x + deltaX
	transform.value.y = panStartTransform.value.y + deltaY
}

const handleMouseUp = () => {
	if (activeNodeDrag.value) {
		justDraggedNodeId.value = activeNodeDrag.value.moved ? activeNodeDrag.value.nodeId : null
		activeNodeDrag.value = null
	}

	isDragging.value = false
}

const applyZoomAtContainerPoint = (newScale: number, containerX: number, containerY: number) => {
	const oldScale = transform.value.scale
	if (newScale === oldScale) {
		return
	}

	const worldX = (containerX - transform.value.x) / oldScale
	const worldY = (containerY - transform.value.y) / oldScale

	transform.value.scale = newScale
	transform.value.x = containerX - worldX * newScale
	transform.value.y = containerY - worldY * newScale
}

const getContainerCenter = (): { x: number; y: number } => {
	if (!chartContainer.value) {
		return { x: svgWidth.value / 2, y: svgHeight.value / 2 }
	}

	return {
		x: chartContainer.value.clientWidth / 2,
		y: chartContainer.value.clientHeight / 2,
	}
}

const getLayoutBounds = (): { minX: number; minY: number; maxX: number; maxY: number } => {
	if (layout.value.nodes.length === 0) {
		return {
			minX: 0,
			minY: 0,
			maxX: svgWidth.value,
			maxY: svgHeight.value,
		}
	}

	let minX = Number.POSITIVE_INFINITY
	let minY = Number.POSITIVE_INFINITY
	let maxX = Number.NEGATIVE_INFINITY
	let maxY = Number.NEGATIVE_INFINITY

	for (const node of layout.value.nodes) {
		minX = Math.min(minX, node.x)
		minY = Math.min(minY, node.y)
		maxX = Math.max(maxX, node.x + nodeWidth)
		maxY = Math.max(maxY, node.y + nodeHeight)
	}

	return { minX, minY, maxX, maxY }
}

const getSvgPointFromClient = (
	clientX: number,
	clientY: number
): { x: number; y: number } | null => {
	if (!svgElement.value) {
		return null
	}

	const ctm = svgElement.value.getScreenCTM()
	if (!ctm) {
		return null
	}

	const point = svgElement.value.createSVGPoint()
	point.x = clientX
	point.y = clientY
	const svgPoint = point.matrixTransform(ctm.inverse())

	return { x: svgPoint.x, y: svgPoint.y }
}

const getViewportCenterInSvg = (): { x: number; y: number } => {
	if (!chartContainer.value) {
		return { x: svgWidth.value / 2, y: svgHeight.value / 2 }
	}

	const rect = chartContainer.value.getBoundingClientRect()
	const centerX = rect.left + rect.width / 2
	const centerY = rect.top + rect.height / 2
	return (
		getSvgPointFromClient(centerX, centerY) || { x: svgWidth.value / 2, y: svgHeight.value / 2 }
	)
}

const animateTransformTo = (targetX: number, targetY: number, targetScale: number) => {
	const start = performance.now()
	const initial = { ...transform.value }
	const distance = Math.hypot(targetX - initial.x, targetY - initial.y)
	const scaleDistance = Math.abs(targetScale - initial.scale) * 240
	const duration = Math.min(
		maxFocusAnimationDuration,
		Math.max(minFocusAnimationDuration, 260 + (distance + scaleDistance) * 0.12)
	)

	if (focusAnimationFrame !== null) {
		cancelAnimationFrame(focusAnimationFrame)
		focusAnimationFrame = null
		motionBlurPx.value = 0
	}

	let lastX = initial.x
	let lastY = initial.y
	let lastScale = initial.scale
	let lastTime = start

	const step = (now: number) => {
		const elapsed = Math.min(1, (now - start) / duration)
		const eased =
			elapsed < 0.5 ? 4 * elapsed * elapsed * elapsed : 1 - Math.pow(-2 * elapsed + 2, 3) / 2

		const nextX = initial.x + (targetX - initial.x) * eased
		const nextY = initial.y + (targetY - initial.y) * eased
		const nextScale = initial.scale + (targetScale - initial.scale) * eased
		const dt = Math.max(1, now - lastTime)
		const panVelocity = Math.hypot(nextX - lastX, nextY - lastY) / dt
		const scaleVelocity = Math.abs(nextScale - lastScale) / dt
		const frameBlur = Math.min(3, panVelocity * 0.25 + scaleVelocity * 55)

		motionBlurPx.value = motionBlurPx.value * 0.65 + frameBlur * 0.35
		transform.value.x = nextX
		transform.value.y = nextY
		transform.value.scale = nextScale
		lastX = nextX
		lastY = nextY
		lastScale = nextScale
		lastTime = now

		if (elapsed < 1) {
			focusAnimationFrame = requestAnimationFrame(step)
			return
		}

		focusAnimationFrame = null
		motionBlurPx.value = 0
	}

	focusAnimationFrame = requestAnimationFrame(step)
}

const chartMotionStyle = computed(() => {
	if (motionBlurPx.value <= 0.02) {
		return {}
	}

	return {
		filter: `blur(${motionBlurPx.value.toFixed(2)}px)`,
	}
})

const focusNodeInView = (nodeId: string, preferredScale: number = transform.value.scale) => {
	if (!chartContainer.value) {
		return
	}

	const targetNode = layout.value.nodes.find((layoutNode) => layoutNode.node.id === nodeId)
	if (!targetNode) {
		return
	}

	const scale = Math.min(Math.max(preferredScale, minScale), maxScale)
	const nodeCenterX = targetNode.x + nodeWidth / 2
	const nodeCenterY = targetNode.y + nodeHeight / 2
	const viewportCenter = getViewportCenterInSvg()

	const targetX = viewportCenter.x - nodeCenterX * scale
	const targetY = viewportCenter.y - nodeCenterY * scale
	animateTransformTo(targetX, targetY, scale)
	focusedNodeId.value = nodeId
}

const focusSearchResult = (index: number) => {
	const total = searchResultIds.value.length
	if (total === 0) {
		return
	}

	const normalized = ((index % total) + total) % total
	searchResultIndex.value = normalized
	const nodeId = searchResultIds.value[normalized]
	focusNodeInView(nodeId, Math.max(transform.value.scale, 1.25))
}

const focusNextSearchResult = () => {
	focusSearchResult(searchResultIndex.value + 1)
}

const focusPreviousSearchResult = () => {
	focusSearchResult(searchResultIndex.value - 1)
}

const runSearch = () => {
	focusSearchResult(searchResultIndex.value)
}

const clearSearch = () => {
	searchQuery.value = ""
	searchResultIndex.value = 0
}

const isTextInputElement = (target: EventTarget | null): boolean => {
	const element = target as HTMLElement | null
	if (!element) {
		return false
	}

	const tagName = element.tagName
	return tagName === "INPUT" || tagName === "TEXTAREA" || element.isContentEditable
}

const handleWheel = (e: WheelEvent) => {
	const delta = e.deltaY > 0 ? zoomOutStep : zoomInStep
	const newScale = Math.min(Math.max(transform.value.scale * delta, minScale), maxScale)
	const didScaleChange = newScale !== transform.value.scale

	e.preventDefault()

	if (!didScaleChange) {
		transform.value.x -= e.deltaX
		transform.value.y -= e.deltaY
		return
	}

	const rect = chartContainer.value?.getBoundingClientRect()
	if (rect) {
		const mouseX = e.clientX - rect.left
		const mouseY = e.clientY - rect.top
		applyZoomAtContainerPoint(newScale, mouseX, mouseY)
		return
	}

	const center = getContainerCenter()
	applyZoomAtContainerPoint(newScale, center.x, center.y)
}

const handleNodeToggle = (id: string) => {
	if (!enableNodeToggle) {
		return
	}

	const target = findNodeById(id, displayNodes.value)
	if (!target || (target.reports?.length || 0) === 0) {
		return
	}

	if (expandedNodes.value.has(id)) {
		expandedNodes.value.delete(id)
	} else {
		expandedNodes.value.add(id)
	}
	expandedNodes.value = new Set(expandedNodes.value)
}

const handleNodeFocus = (node: OrgNodeData) => {
	focusedNodeId.value = node.id
}

const handleNodeClick = (node: OrgNodeData) => {
	if (activeNodeDrag.value?.nodeId === node.id) {
		return
	}

	if (justDraggedNodeId.value === node.id) {
		justDraggedNodeId.value = null
		return
	}

	emit("nodeClick", node)
}

const handleNodeDragStart = (payload: NodeDragStartPayload) => {
	const offset = getNodeOffset(payload.node.id)
	activeNodeDrag.value = {
		nodeId: payload.node.id,
		startClientX: payload.clientX,
		startClientY: payload.clientY,
		originX: offset.x,
		originY: offset.y,
		moved: false,
	}
	focusedNodeId.value = payload.node.id
}

const handleKeydown = (e: KeyboardEvent) => {
	if (isTextInputElement(e.target)) {
		return
	}

	if (visibleFlatNodes.value.length === 0) {
		return
	}

	const lastIndex = visibleFlatNodes.value.length - 1
	const focusedIndex = focusedNodeId.value
		? visibleFlatNodes.value.findIndex((n) => n.id === focusedNodeId.value)
		: -1
	const currentIndex = focusedIndex === -1 ? 0 : focusedIndex

	if (e.key === "ArrowDown" || e.key === "ArrowRight") {
		e.preventDefault()
		const nextIndex = Math.min(currentIndex + 1, lastIndex)
		const nextNodeId = visibleFlatNodes.value[nextIndex].id
		focusNodeInView(nextNodeId)
	} else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
		e.preventDefault()
		const prevIndex = Math.max(currentIndex - 1, 0)
		const prevNodeId = visibleFlatNodes.value[prevIndex].id
		focusNodeInView(prevNodeId)
	} else if (e.key === "Enter") {
		const node = visibleFlatNodes.value[currentIndex]
		if (enableNodeToggle && node.reports && node.reports.length > 0) {
			handleNodeToggle(node.id)
		} else {
			handleNodeClick(node)
		}
	}
}

const resetView = () => {
	centerChart()
}

const zoomIn = () => {
	const newScale = Math.min(transform.value.scale * zoomInStep, maxScale)
	const center = getContainerCenter()
	applyZoomAtContainerPoint(newScale, center.x, center.y)
}

const zoomOut = () => {
	const newScale = Math.max(transform.value.scale * zoomOutStep, minScale)
	const center = getContainerCenter()
	applyZoomAtContainerPoint(newScale, center.x, center.y)
}

const getNodePosition = (nodeId: string): { x: number; y: number } | null => {
	const findNode = (nodes: LayoutNode[]): LayoutNode | null => {
		for (const ln of nodes) {
			if (ln.node.id === nodeId) return ln
			const found = findNode(ln.children)
			if (found) return found
		}
		return null
	}

	const found = findNode(layout.value.nodes)
	return found ? { x: found.x, y: found.y } : null
}
</script>

<template>
	<div class="org-chart-wrapper">
		<div class="org-chart-search">
			<input
				v-model="searchQuery"
				type="text"
				class="search-input"
				placeholder="Search The Employee or Jobdesks"
				@keydown.enter.prevent="runSearch" />
			<button
				class="search-btn"
				:disabled="searchResultIds.length === 0"
				title="Previous result"
				@click="focusPreviousSearchResult">
				↑
			</button>
			<button
				class="search-btn"
				:disabled="searchResultIds.length === 0"
				title="Next result"
				@click="focusNextSearchResult">
				↓
			</button>
			<button
				class="search-btn search-find-btn"
				:disabled="searchResultIds.length === 0"
				@click="runSearch">
				Find
			</button>
			<button v-if="searchQuery" class="search-btn search-clear-btn" @click="clearSearch">
				Clear
			</button>
			<span class="search-count">
				{{
					searchResultIds.length === 0
						? "0"
						: `${searchResultIndex + 1}/${searchResultIds.length}`
				}}
			</span>
		</div>

		<div class="org-chart-controls">
			<button class="control-btn" @click="zoomIn" title="Zoom In">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2">
					<circle cx="11" cy="11" r="8"></circle>
					<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
					<line x1="11" y1="8" x2="11" y2="14"></line>
					<line x1="8" y1="11" x2="14" y2="11"></line>
				</svg>
			</button>
			<button class="control-btn" @click="zoomOut" title="Zoom Out">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2">
					<circle cx="11" cy="11" r="8"></circle>
					<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
					<line x1="8" y1="11" x2="14" y2="11"></line>
				</svg>
			</button>
			<button class="control-btn" @click="resetView" title="Reset View">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2">
					<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
					<path d="M3 3v5h5"></path>
				</svg>
			</button>
		</div>

		<div
			ref="chartContainer"
			class="org-chart-container"
			@mousedown="handleMouseDown"
			@mousemove="handleMouseMove"
			@mouseup="handleMouseUp"
			@mouseleave="handleMouseUp"
			@wheel="handleWheel">
			<svg
				ref="svgElement"
				class="org-chart-svg"
				:width="svgWidth"
				:height="svgHeight"
				:viewBox="`0 0 ${svgWidth} ${svgHeight}`"
				preserveAspectRatio="xMidYMid meet">
				<g
					class="org-chart-motion-layer"
					:style="chartMotionStyle"
					:transform="`translate(${transform.x}, ${transform.y}) scale(${transform.scale})`">
					<g class="org-chart-connectors">
						<path
							v-for="(line, index) in layout.lines"
							:key="`line-${index}`"
							:d="`M ${line.x1} ${line.y1} L ${line.x1} ${line.y1 + (line.y2 - line.y1) / 2} L ${line.x2} ${line.y1 + (line.y2 - line.y1) / 2} L ${line.x2} ${line.y2}`"
							class="connector-line" />
					</g>

					<foreignObject
						v-for="ln in layout.nodes"
						:key="ln.node.id"
						:x="ln.x"
						:y="ln.y"
						:width="nodeWidth"
						:height="nodeHeight">
						<OrgNode
							:node="ln.node"
							:is-focused="focusedNodeId === ln.node.id"
							:is-expanded="enableNodeToggle ? expandedNodes.has(ln.node.id) : true"
							:has-children="(ln.node.reports?.length ?? 0) > 0"
							:disable-toggle="!enableNodeToggle"
							@toggle="handleNodeToggle"
							@focus="handleNodeFocus"
							@drag-start="handleNodeDragStart"
							@select="handleNodeClick" />
					</foreignObject>
				</g>
			</svg>
		</div>

		<div class="org-chart-help">
			<span>🖱️Drag to pan</span>
			<span>🖱️Scroll to zoom</span>
			<span>⌨️ Arrow keys to navigate</span>
			<span>👍Enter to select</span>
			<span>👌Click The node to Move Around</span>
			<span>✨Click reset to re-center</span>
		</div>
	</div>
</template>

<style scoped>
.org-chart-wrapper {
	position: relative;
	width: 100%;
	height: 600px;
	background: #f9fafb;
	border-radius: 12px;
	overflow: hidden;
	border: 1px solid #e5e7eb;
}

.org-chart-wrapper,
.org-chart-wrapper * {
	user-select: none;
	-webkit-user-select: none;
}

.org-chart-controls {
	position: absolute;
	top: 16px;
	right: 16px;
	z-index: 10;
	display: flex;
	gap: 8px;
	background: white;
	padding: 8px;
	border-radius: 8px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.org-chart-search {
	position: absolute;
	top: 16px;
	left: 16px;
	z-index: 10;
	display: flex;
	align-items: center;
	gap: 8px;
	background: white;
	padding: 8px;
	border-radius: 8px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	max-width: calc(100% - 170px);
}

.search-input {
	width: 230px;
	max-width: 36vw;
	padding: 8px 10px;
	border: 1px solid #d1d5db;
	border-radius: 6px;
	font-size: 13px;
	color: #111827;
	background: white;
	outline: none;
	user-select: text;
	-webkit-user-select: text;
}

.search-input:focus {
	border-color: #6366f1;
	box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.search-btn {
	border: none;
	background: #f3f4f6;
	color: #374151;
	border-radius: 6px;
	padding: 8px 10px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	min-width: 34px;
}

.search-btn:hover:not(:disabled) {
	background: #e5e7eb;
}

.search-btn:disabled {
	opacity: 0.45;
	cursor: not-allowed;
}

.search-find-btn {
	background: #dbeafe;
	color: #1e40af;
}

.search-find-btn:hover:not(:disabled) {
	background: #bfdbfe;
}

.search-clear-btn {
	background: #fee2e2;
	color: #b91c1c;
}

.search-clear-btn:hover {
	background: #fecaca;
}

.search-count {
	font-size: 12px;
	color: #6b7280;
	min-width: 40px;
	text-align: right;
}

.control-btn {
	width: 36px;
	height: 36px;
	border: none;
	background: #f3f4f6;
	border-radius: 6px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s ease;
}

.control-btn:hover {
	background: #e5e7eb;
}

.control-btn svg {
	width: 20px;
	height: 20px;
	color: #4b5563;
}

.org-chart-container {
	width: 100%;
	height: 100%;
	cursor: grab;
	overflow: hidden;
}

.org-chart-container:active {
	cursor: grabbing;
}

.org-chart-svg {
	width: 100%;
	height: 100%;
}

.org-chart-motion-layer {
	will-change: transform, filter;
}

.connector-line {
	fill: none;
	stroke: #d1d5db;
	stroke-width: 2;
}

.org-chart-help {
	position: absolute;
	bottom: 16px;
	left: 16px;
	z-index: 10;
	display: flex;
	gap: 16px;
	background: white;
	padding: 8px 16px;
	border-radius: 8px;
	font-size: 12px;
	color: #6b7280;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
	.org-chart-wrapper {
		height: 500px;
	}

	.org-chart-search {
		top: 12px;
		left: 12px;
		right: 12px;
		max-width: unset;
		flex-wrap: wrap;
	}

	.search-input {
		flex: 1;
		max-width: unset;
		min-width: 180px;
	}

	.org-chart-help {
		flex-wrap: wrap;
		gap: 8px;
		font-size: 11px;
	}
}
</style>
