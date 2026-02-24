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

const dummyData: OrgNodeData[] = [
	{
		id: "1",
		name: "Sarah Johnson",
		title: "Head of Human Resources",
		department: "Human Resources",
		avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=Sarah",
		reports: [
			{
				id: "2",
				name: "Michael Chen",
				title: "HR Manager",
				department: "Human Resources",
				avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=Michael",
				reports: [
					{
						id: "5",
						name: "Emily Davis",
						title: "HR Specialist",
						department: "Recruitment",
						avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=Emily",
						reports: [],
					},
					{
						id: "6",
						name: "James Wilson",
						title: "HR Coordinator",
						department: "Employee Relations",
						avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=James",
						reports: [],
					},
				],
			},
			{
				id: "3",
				name: "Lisa Anderson",
				title: "Training & Development Lead",
				department: "Learning & Development",
				avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=Lisa",
				reports: [
					{
						id: "7",
						name: "David Brown",
						title: "Training Coordinator",
						department: "Learning & Development",
						avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=David",
						reports: [],
					},
				],
			},
			{
				id: "4",
				name: "Robert Taylor",
				title: "Payroll Manager",
				department: "Payroll",
				avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=Robert",
				reports: [
					{
						id: "8",
						name: "Jennifer Martinez",
						title: "Payroll Specialist",
						department: "Payroll",
						avatarUrl: "https://api.dicebear.com/7.x/avataaars/svg?seed=Jennifer",
						reports: [],
					},
				],
			},
		],
	},
]

const chartContainer = ref<HTMLElement | null>(null)
const transform = ref({ x: 0, y: 0, scale: 1 })
const isDragging = ref(false)
const dragStart = ref({ x: 0, y: 0 })
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
	return props.nodes && props.nodes.length > 0 ? props.nodes : dummyData
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

watch(
	displayNodes,
	(nodes) => {
		expandedNodes.value = getInitialExpandedNodes(nodes, 1)
		focusedNodeId.value = nodes[0]?.id || null
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

onMounted(() => {
	if (chartContainer.value) {
		svgWidth.value = Math.max(chartContainer.value.clientWidth, 1200)
		svgHeight.value = Math.max(chartContainer.value.clientHeight, 800)
	}
	window.addEventListener("keydown", handleKeydown)

	// Center chart after layout is computed
	setTimeout(centerChart, 100)
})

onUnmounted(() => {
	window.removeEventListener("keydown", handleKeydown)
})

const centerChart = () => {
	if (!chartContainer.value) return

	const containerWidth = chartContainer.value.clientWidth
	const containerHeight = chartContainer.value.clientHeight

	// Calculate scale to fit if needed
	const scaleX = containerWidth / svgWidth.value
	const scaleY = containerHeight / svgHeight.value
	const fitScale = Math.min(scaleX, scaleY, 1) * 0.9 // 90% to add padding

	transform.value.scale = Math.max(0.3, Math.min(fitScale, 1))

	// Center the tree
	transform.value.x = (containerWidth - svgWidth.value * transform.value.scale) / 2
	transform.value.y = (containerHeight - svgHeight.value * transform.value.scale) / 2
}

// Re-center when nodes are expanded/collapsed
watch(
	expandedNodes,
	() => {
		setTimeout(centerChart, 50)
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
	isDragging.value = true
	dragStart.value = { x: e.clientX - transform.value.x, y: e.clientY - transform.value.y }
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
	transform.value.x = e.clientX - dragStart.value.x
	transform.value.y = e.clientY - dragStart.value.y
}

const handleMouseUp = () => {
	if (activeNodeDrag.value) {
		justDraggedNodeId.value = activeNodeDrag.value.moved ? activeNodeDrag.value.nodeId : null
		activeNodeDrag.value = null
	}

	isDragging.value = false
}

const handleWheel = (e: WheelEvent) => {
	e.preventDefault()
	const delta = e.deltaY > 0 ? 0.9 : 1.1
	const newScale = Math.min(Math.max(transform.value.scale * delta, 0.3), 3)

	const rect = chartContainer.value?.getBoundingClientRect()
	if (rect) {
		const mouseX = e.clientX - rect.left
		const mouseY = e.clientY - rect.top

		const scaleDiff = newScale - transform.value.scale
		transform.value.x -= (mouseX * scaleDiff) / transform.value.scale
		transform.value.y -= (mouseY * scaleDiff) / transform.value.scale
	}

	transform.value.scale = newScale
}

const handleNodeToggle = (id: string) => {
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
	if (!focusedNodeId.value) {
		if (visibleFlatNodes.value.length > 0) {
			focusedNodeId.value = visibleFlatNodes.value[0].id
		}
		return
	}

	const currentIndex = visibleFlatNodes.value.findIndex((n) => n.id === focusedNodeId.value)
	if (currentIndex === -1) return

	if (e.key === "ArrowDown" || e.key === "ArrowRight") {
		e.preventDefault()
		const nextIndex = Math.min(currentIndex + 1, visibleFlatNodes.value.length - 1)
		focusedNodeId.value = visibleFlatNodes.value[nextIndex].id
	} else if (e.key === "ArrowUp" || e.key === "ArrowLeft") {
		e.preventDefault()
		const prevIndex = Math.max(currentIndex - 1, 0)
		focusedNodeId.value = visibleFlatNodes.value[prevIndex].id
	} else if (e.key === "Enter") {
		const node = visibleFlatNodes.value[currentIndex]
		if (node.reports && node.reports.length > 0) {
			handleNodeToggle(node.id)
		} else {
			handleNodeClick(node)
		}
	}
}

const resetView = () => {
	transform.value = { x: 0, y: 0, scale: 1 }
}

const zoomIn = () => {
	transform.value.scale = Math.min(transform.value.scale * 1.2, 3)
}

const zoomOut = () => {
	transform.value.scale = Math.max(transform.value.scale * 0.8, 0.3)
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
				class="org-chart-svg"
				:width="svgWidth"
				:height="svgHeight"
				:viewBox="`0 0 ${svgWidth} ${svgHeight}`"
				preserveAspectRatio="xMidYMid meet">
				<g
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
								:is-expanded="expandedNodes.has(ln.node.id)"
								:has-children="(ln.node.reports?.length ?? 0) > 0"
								@toggle="handleNodeToggle"
								@focus="handleNodeFocus"
								@drag-start="handleNodeDragStart"
								@select="handleNodeClick" />
						</foreignObject>
					</g>
				</svg>
			</div>

			<div class="org-chart-help">
				<span>🖱️ Drag to pan</span>
				<span>⚙️ Scroll to zoom</span>
				<span>⌨️ Arrow keys to navigate</span>
				<span>Enter to expand</span>
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

	.org-chart-help {
		flex-wrap: wrap;
		gap: 8px;
		font-size: 11px;
	}
}
</style>
