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

interface EmployeePayload {
	group: OrgNodeData
	employee: OrgNodeData
}

interface LayoutNode {
	node: OrgNodeData
	x: number
	y: number
	children: LayoutNode[]
}

interface NavigationTarget {
	key: string
	type: "group" | "employee"
	groupId: string
	node: OrgNodeData
	searchableText: string
}

interface LayoutLine {
	x1: number
	y1: number
	x2: number
	y2: number
}

const props = defineProps<{
	nodes?: OrgNodeData[]
}>()

const emit = defineEmits<{
	nodeClick: [node: OrgNodeData]
}>()

const employeeLimit = 10
const chartContainer = ref<HTMLElement | null>(null)
const svgElement = ref<SVGSVGElement | null>(null)
const transform = ref({ x: 0, y: 0, scale: 1 })
const focusedNodeId = ref<string | null>(null)
const focusedEmployeeId = ref<string | null>(null)
const focusedNavigationKey = ref<string | null>(null)
const svgWidth = ref(1200)
const svgHeight = ref(800)
const containerWidth = ref(1200)
const containerHeight = ref(800)
const searchQuery = ref("")
const searchResultIndex = ref(0)
let focusAnimationFrame: number | null = null
let searchBlurAnimationFrame: number | null = null
let focusScrollAnimationFrame: number | null = null
const minFocusAnimationDuration = 280
const maxFocusAnimationDuration = 700
const motionBlurPx = ref(0)
let resizeObserver: ResizeObserver | null = null
let centerChartTimer: ReturnType<typeof setTimeout> | null = null

const nodeTopOffset = 40
const verticalGroupGap = 40
const minScale = 0.2
const maxScale = Number.POSITIVE_INFINITY
const zoomInStep = 1.35
const zoomOutStep = 0.75
const defaultScale = 1.8
const searchFocusDuration = 560

const displayNodes = computed(() => {
	return props.nodes ?? []
})

const getVisibleEmployees = (group: OrgNodeData): OrgNodeData[] => {
	return (group.reports ?? []).slice(0, employeeLimit)
}

const getGridColumns = (): number => {
	if (containerWidth.value >= 1024) {
		return 3
	}

	if (containerWidth.value >= 600) {
		return 2
	}

	return 1
}

const chartNodeWidth = computed(() => {
	const columns = getGridColumns()
	if (columns === 3) {
		return 640
	}

	if (columns === 2) {
		return 460
	}

	return 300
})

const maxVisibleEmployeesPerNode = computed(() => {
	if (displayNodes.value.length === 0) {
		return 0
	}

	return Math.max(...displayNodes.value.map((group) => getVisibleEmployees(group).length), 0)
})

const chartNodeHeight = computed(() => {
	const rows = Math.ceil(maxVisibleEmployeesPerNode.value / getGridColumns())
	const employeeCardHeight = 54
	const employeeGap = 10
	const headerHeight = 96
	const connectorHeight = 16
	const bottomPadding = 18
	const gridHeight = rows > 0 ? rows * employeeCardHeight + (rows - 1) * employeeGap : 0

	return Math.max(170, headerHeight + connectorHeight + gridHeight + bottomPadding)
})

const calculateLayout = (nodes: OrgNodeData[], x: number, y: number): LayoutNode[] => {
	if (nodes.length === 0) {
		return []
	}

	return nodes.map((node, index) => {
		return {
			node,
			x,
			y: y + index * (chartNodeHeight.value + verticalGroupGap),
			children: [],
		}
	})
}

const calculateSubtreeWidth = (_layoutNodes: LayoutNode[]): number => {
	return chartNodeWidth.value
}

const renderTree = (layoutNodes: LayoutNode[]): { nodes: LayoutNode[]; lines: LayoutLine[] } => {
	return {
		nodes: layoutNodes,
		lines: [],
	}
}

const layout = computed(() => {
	const tempLayout = calculateLayout(displayNodes.value, 0, nodeTopOffset)
	const totalTreeWidth = calculateSubtreeWidth(tempLayout)
	const startX = (Math.max(containerWidth.value, totalTreeWidth) - totalTreeWidth) / 2
	const treeLayout = calculateLayout(displayNodes.value, startX, nodeTopOffset)

	const maxX = calculateMaxX(treeLayout, 0)
	const maxY = calculateMaxY(treeLayout, 0)
	const contentWidth = maxX + chartNodeWidth.value + 100
	const contentHeight = maxY + chartNodeHeight.value + 100
	svgWidth.value = Math.max(containerWidth.value, contentWidth * transform.value.scale)
	svgHeight.value = Math.max(containerHeight.value, contentHeight * transform.value.scale)

	return renderTree(treeLayout)
})

const calculateMaxX = (layoutNodes: LayoutNode[], maxX: number): number => {
	for (const layoutNode of layoutNodes) {
		maxX = Math.max(maxX, layoutNode.x)
	}

	return maxX
}

const calculateMaxY = (layoutNodes: LayoutNode[], maxY: number): number => {
	for (const layoutNode of layoutNodes) {
		maxY = Math.max(maxY, layoutNode.y)
	}

	return maxY
}

const navigationTargets = computed<NavigationTarget[]>(() => {
	const targets: NavigationTarget[] = []

	for (const group of displayNodes.value) {
		targets.push({
			key: `group:${group.id}`,
			type: "group",
			groupId: group.id,
			node: group,
			searchableText: [group.name, group.title, group.department]
				.filter(Boolean)
				.join(" ")
				.toLowerCase(),
		})

		for (const employee of getVisibleEmployees(group)) {
			targets.push({
				key: `employee:${group.id}:${employee.id}`,
				type: "employee",
				groupId: group.id,
				node: employee,
				searchableText: [employee.name, employee.title, employee.department, group.name]
					.filter(Boolean)
					.join(" ")
					.toLowerCase(),
			})
		}
	}

	return targets
})

const navigationTargetMap = computed(() => {
	return new Map(navigationTargets.value.map((target) => [target.key, target]))
})

const searchResultKeys = computed(() => {
	const keyword = searchQuery.value.trim().toLowerCase()
	if (!keyword) {
		return []
	}

	return navigationTargets.value
		.filter((target) => target.searchableText.includes(keyword))
		.map((target) => target.key)
})

const setFocusedTarget = (target: NavigationTarget) => {
	focusedNavigationKey.value = target.key
	focusedNodeId.value = target.groupId
	focusedEmployeeId.value = target.type === "employee" ? target.node.id : null
}

const clearFocusedTarget = () => {
	focusedNavigationKey.value = null
	focusedNodeId.value = null
	focusedEmployeeId.value = null
}

watch(
	displayNodes,
	() => {
		if (focusAnimationFrame !== null) {
			cancelAnimationFrame(focusAnimationFrame)
			focusAnimationFrame = null
		}

		motionBlurPx.value = 0

		requestAnimationFrame(() => {
			scheduleCenterChart()
		})
	},
	{ immediate: true }
)

watch(
	navigationTargets,
	(targets) => {
		if (targets.length === 0) {
			clearFocusedTarget()
			return
		}

		if (focusedNavigationKey.value && navigationTargetMap.value.has(focusedNavigationKey.value)) {
			return
		}

		setFocusedTarget(targets[0])
	},
	{ immediate: true }
)

watch(searchQuery, () => {
	searchResultIndex.value = 0
})

watch(searchResultKeys, (resultKeys) => {
	if (resultKeys.length === 0) {
		searchResultIndex.value = 0
		return
	}

	if (searchResultIndex.value >= resultKeys.length) {
		searchResultIndex.value = 0
	}
})

onMounted(() => {
	if (chartContainer.value) {
		containerWidth.value = Math.max(chartContainer.value.clientWidth, 1200)
		containerHeight.value = Math.max(chartContainer.value.clientHeight, 800)
		svgWidth.value = containerWidth.value
		svgHeight.value = containerHeight.value
		resizeObserver = new ResizeObserver(() => {
			if (!chartContainer.value) {
				return
			}

			containerWidth.value = Math.max(chartContainer.value.clientWidth, 1200)
			containerHeight.value = Math.max(chartContainer.value.clientHeight, 800)
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

	if (searchBlurAnimationFrame !== null) {
		cancelAnimationFrame(searchBlurAnimationFrame)
		searchBlurAnimationFrame = null
	}

	if (focusScrollAnimationFrame !== null) {
		cancelAnimationFrame(focusScrollAnimationFrame)
		focusScrollAnimationFrame = null
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

	const currentContainerWidth = chartContainer.value.clientWidth
	const currentContainerHeight = chartContainer.value.clientHeight
	if (currentContainerWidth <= 0 || currentContainerHeight <= 0) {
		return false
	}

	const bounds = getLayoutBounds()
	const boundsWidth = Math.max(1, bounds.maxX - bounds.minX)
	const boundsHeight = Math.max(1, bounds.maxY - bounds.minY)
	const fitScale = Math.min(currentContainerWidth / boundsWidth, currentContainerHeight / boundsHeight)
	const autoScale = Math.max(minScale, fitScale * 1.4)
	const scale = Math.min(Math.max(Math.max(defaultScale, autoScale), minScale), maxScale)
	const centerX = bounds.minX + boundsWidth / 2
	const centerY = bounds.minY + boundsHeight / 2

	transform.value.scale = scale
	centerWorldPoint(centerX, centerY, scale, "auto")

	return true
}

const applyZoomAtContainerPoint = (newScale: number, containerX: number, containerY: number) => {
	if (!chartContainer.value) {
		transform.value.scale = newScale
		return
	}

	const oldScale = transform.value.scale
	if (newScale === oldScale) {
		return
	}

	const container = chartContainer.value
	const worldX = (container.scrollLeft + containerX) / oldScale
	const worldY = (container.scrollTop + containerY) / oldScale

	if (focusScrollAnimationFrame !== null) {
		cancelAnimationFrame(focusScrollAnimationFrame)
		focusScrollAnimationFrame = null
	}

	transform.value.scale = newScale
	const targetLeft = worldX * newScale - containerX
	const targetTop = worldY * newScale - containerY
	container.scrollTo({
		left: targetLeft,
		top: targetTop,
		behavior: "auto",
	})

	requestAnimationFrame(() => {
		if (!chartContainer.value) {
			return
		}

		const nextContainer = chartContainer.value
		const clamped = getClampedScrollPosition(nextContainer, targetLeft, targetTop)
		nextContainer.scrollTo({
			left: clamped.left,
			top: clamped.top,
			behavior: "auto",
		})
	})
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
		maxX = Math.max(maxX, node.x + chartNodeWidth.value)
		maxY = Math.max(maxY, node.y + chartNodeHeight.value)
	}

	return { minX, minY, maxX, maxY }
}

const getClampedScrollPosition = (
	container: HTMLElement,
	left: number,
	top: number
): { left: number; top: number } => {
	const maxLeft = Math.max(0, container.scrollWidth - container.clientWidth)
	const maxTop = Math.max(0, container.scrollHeight - container.clientHeight)

	return {
		left: Math.min(Math.max(0, left), maxLeft),
		top: Math.min(Math.max(0, top), maxTop),
	}
}

const animateContainerScrollTo = (
	container: HTMLElement,
	left: number,
	top: number,
	duration: number
) => {
	const clamped = getClampedScrollPosition(container, left, top)

	if (duration <= 0) {
		container.scrollTo({
			left: clamped.left,
			top: clamped.top,
			behavior: "auto",
		})
		return
	}

	const start = performance.now()
	const startLeft = container.scrollLeft
	const startTop = container.scrollTop
	const deltaLeft = clamped.left - startLeft
	const deltaTop = clamped.top - startTop

	if (Math.abs(deltaLeft) < 0.5 && Math.abs(deltaTop) < 0.5) {
		container.scrollTo({
			left: clamped.left,
			top: clamped.top,
			behavior: "auto",
		})
		return
	}

	if (focusScrollAnimationFrame !== null) {
		cancelAnimationFrame(focusScrollAnimationFrame)
		focusScrollAnimationFrame = null
	}

	const step = (now: number) => {
		const progress = Math.min(1, (now - start) / duration)
		const eased =
			progress < 0.5
				? 4 * progress * progress * progress
				: 1 - Math.pow(-2 * progress + 2, 3) / 2
		const nextLeft = startLeft + deltaLeft * eased
		const nextTop = startTop + deltaTop * eased

		container.scrollTo({
			left: nextLeft,
			top: nextTop,
			behavior: "auto",
		})

		if (progress < 1) {
			focusScrollAnimationFrame = requestAnimationFrame(step)
			return
		}

		focusScrollAnimationFrame = null
	}

	focusScrollAnimationFrame = requestAnimationFrame(step)
}

const centerWorldPoint = (
	worldX: number,
	worldY: number,
	scale: number,
	behavior: ScrollBehavior = "smooth",
	duration: number = searchFocusDuration
) => {
	requestAnimationFrame(() => {
		requestAnimationFrame(() => {
			if (!chartContainer.value) {
				return
			}

			const container = chartContainer.value
			const targetLeft = worldX * scale - container.clientWidth / 2
			const targetTop = worldY * scale - container.clientHeight / 2
			const clamped = getClampedScrollPosition(container, targetLeft, targetTop)

			if (behavior === "smooth") {
				animateContainerScrollTo(container, clamped.left, clamped.top, duration)
			} else {
				container.scrollTo({
					left: clamped.left,
					top: clamped.top,
					behavior: "auto",
				})
			}
		})
	})
}

const centerNodeElement = (
	nodeId: string,
	behavior: ScrollBehavior = "smooth",
	duration: number = searchFocusDuration
): boolean => {
	if (!chartContainer.value) {
		return false
	}

	const container = chartContainer.value
	const nodeElements = container.querySelectorAll<SVGForeignObjectElement>("foreignObject[data-node-id]")
	let nodeElement: SVGForeignObjectElement | null = null

	for (const element of nodeElements) {
		if (element.dataset.nodeId === nodeId) {
			nodeElement = element
			break
		}
	}

	if (!nodeElement) {
		return false
	}

	const containerRect = container.getBoundingClientRect()
	const nodeRect = nodeElement.getBoundingClientRect()
	const targetLeft =
		container.scrollLeft +
		(nodeRect.left - containerRect.left) -
		(container.clientWidth - nodeRect.width) / 2
	const targetTop =
		container.scrollTop +
		(nodeRect.top - containerRect.top) -
		(container.clientHeight - nodeRect.height) / 2
	const clamped = getClampedScrollPosition(container, targetLeft, targetTop)

	if (behavior === "smooth") {
		animateContainerScrollTo(container, clamped.left, clamped.top, duration)
	} else {
		container.scrollTo({
			left: clamped.left,
			top: clamped.top,
			behavior: "auto",
		})
	}

	return true
}

const getSvgPointFromClient = (clientX: number, clientY: number): { x: number; y: number } | null => {
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
	return getSvgPointFromClient(centerX, centerY) || { x: svgWidth.value / 2, y: svgHeight.value / 2 }
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

const focusNodeInView = (
	nodeId: string,
	preferredScale: number = transform.value.scale,
	smoothDuration: number = searchFocusDuration
) => {
	if (!chartContainer.value) {
		return
	}

	const targetNode = layout.value.nodes.find((layoutNode) => layoutNode.node.id === nodeId)
	if (!targetNode) {
		return
	}

	const scale = Math.min(Math.max(preferredScale, minScale), maxScale)
	const nodeCenterX = targetNode.x + chartNodeWidth.value / 2
	const nodeCenterY = targetNode.y + chartNodeHeight.value / 2
	transform.value.scale = scale

	requestAnimationFrame(() => {
		requestAnimationFrame(() => {
			const centered = centerNodeElement(nodeId, "smooth", smoothDuration)
			if (!centered) {
				centerWorldPoint(nodeCenterX, nodeCenterY, scale, "smooth", smoothDuration)
			}
		})
	})
}

const focusTargetByKey = (
	key: string,
	preferredScale: number = transform.value.scale,
	smoothDuration: number = searchFocusDuration
) => {
	const target = navigationTargetMap.value.get(key)
	if (!target) {
		return
	}

	focusNodeInView(target.groupId, preferredScale, smoothDuration)
	setFocusedTarget(target)
}

const focusSearchResult = (index: number, smoothDuration: number = searchFocusDuration) => {
	const total = searchResultKeys.value.length
	if (total === 0) {
		return
	}

	const normalized = ((index % total) + total) % total
	searchResultIndex.value = normalized
	const targetKey = searchResultKeys.value[normalized]
	focusTargetByKey(targetKey, Math.max(transform.value.scale, 1.25), smoothDuration)
}

const focusNextSearchResult = () => {
	focusSearchResult(searchResultIndex.value + 1)
}

const focusPreviousSearchResult = () => {
	focusSearchResult(searchResultIndex.value - 1)
}

const triggerSearchMotionBlur = () => {
	const start = performance.now()
	const duration = 260
	const peakBlur = 2.6

	if (searchBlurAnimationFrame !== null) {
		cancelAnimationFrame(searchBlurAnimationFrame)
		searchBlurAnimationFrame = null
	}

	const step = (now: number) => {
		const progress = Math.min(1, (now - start) / duration)
		motionBlurPx.value = peakBlur * (1 - progress)

		if (progress < 1) {
			searchBlurAnimationFrame = requestAnimationFrame(step)
			return
		}

		searchBlurAnimationFrame = null
		motionBlurPx.value = 0
	}

	searchBlurAnimationFrame = requestAnimationFrame(step)
}

const runSearch = (withMotionBlur: boolean = false) => {
	if (withMotionBlur) {
		triggerSearchMotionBlur()
	}

	focusSearchResult(searchResultIndex.value, 680)
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
	if (!e.ctrlKey && !e.metaKey) {
		return
	}

	const delta = e.deltaY > 0 ? zoomOutStep : zoomInStep
	const newScale = Math.min(Math.max(transform.value.scale * delta, minScale), maxScale)
	const didScaleChange = newScale !== transform.value.scale

	e.preventDefault()

	if (!didScaleChange) {
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

const handleNodeFocus = (node: OrgNodeData) => {
	const target = navigationTargetMap.value.get(`group:${node.id}`)
	if (!target) {
		return
	}

	setFocusedTarget(target)
}

const handleNodeClick = (node: OrgNodeData) => {
	handleNodeFocus(node)
	emit("nodeClick", node)
}

const handleEmployeeFocus = (payload: EmployeePayload) => {
	const target = navigationTargetMap.value.get(`employee:${payload.group.id}:${payload.employee.id}`)
	if (!target) {
		return
	}

	setFocusedTarget(target)
}

const handleEmployeeClick = (payload: EmployeePayload) => {
	handleEmployeeFocus(payload)
	emit("nodeClick", payload.employee)
}

const handleKeydown = (e: KeyboardEvent) => {
	if (isTextInputElement(e.target)) {
		return
	}

	if (navigationTargets.value.length === 0) {
		return
	}

	const currentIndex = focusedNavigationKey.value
		? Math.max(
				navigationTargets.value.findIndex((target) => target.key === focusedNavigationKey.value),
				0
			)
		: 0
	const lastIndex = navigationTargets.value.length - 1

	if (e.key === "ArrowDown" || e.key === "ArrowRight") {
		e.preventDefault()
		const nextIndex = Math.min(currentIndex + 1, lastIndex)
		focusTargetByKey(navigationTargets.value[nextIndex].key)
	} else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
		e.preventDefault()
		const prevIndex = Math.max(currentIndex - 1, 0)
		focusTargetByKey(navigationTargets.value[prevIndex].key)
	} else if (e.key === "Enter") {
		const currentTarget = navigationTargets.value[currentIndex]
		if (!currentTarget) {
			return
		}

		emit("nodeClick", currentTarget.node)
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
</script>

<template>
	<div class="org-chart-wrapper">
		<div class="org-chart-search">
			<input
				v-model="searchQuery"
				type="text"
				class="search-input"
				placeholder="Search The Employee or Jobdesk"
				@keydown.enter.prevent="runSearch(true)" />
			<button
				class="search-btn"
				:disabled="searchResultKeys.length === 0"
				title="Previous result"
				@click="focusPreviousSearchResult">
				↑
			</button>
			<button
				class="search-btn"
				:disabled="searchResultKeys.length === 0"
				title="Next result"
				@click="focusNextSearchResult">
				↓
			</button>
			<button
				class="search-btn search-find-btn"
				:disabled="searchResultKeys.length === 0"
				@click="runSearch">
				Find
			</button>
			<button v-if="searchQuery" class="search-btn search-clear-btn" @click="clearSearch">
				Clear
			</button>
			<span class="search-count">
				{{
					searchResultKeys.length === 0
						? "0"
						: `${searchResultIndex + 1}/${searchResultKeys.length}`
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
					:transform="`scale(${transform.scale})`">
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
						:data-node-id="ln.node.id"
						:x="ln.x"
						:y="ln.y"
						:width="chartNodeWidth"
						:height="chartNodeHeight">
						<OrgNode
							:node="ln.node"
							:is-focused="focusedNodeId === ln.node.id"
							:focused-employee-id="focusedNodeId === ln.node.id ? focusedEmployeeId : null"
							@focus="handleNodeFocus"
							@employee-focus="handleEmployeeFocus"
							@select="handleNodeClick"
							@employee-select="handleEmployeeClick" />
					</foreignObject>
				</g>
			</svg>
		</div>

		<div class="org-chart-help">
			<span>🖱️ Scroll canvas</span>
			<span>🖱️Ctrl + Scroll to zoom</span>
			<span>⌨️ Arrow keys to navigate</span>
			<span>👍Enter to select</span>
			<span>👌Click node to select</span>
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
	overflow-y: auto;
	overflow-x: hidden;
}

.org-chart-svg {
	display: block;
	width: auto;
	height: auto;
	min-width: 100%;
	min-height: 100%;
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
