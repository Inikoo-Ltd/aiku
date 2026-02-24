<script setup lang="ts">
interface OrgNodeData {
	id: string
	name: string
	title: string
	avatarUrl?: string
	department?: string
	reports?: OrgNodeData[]
}

interface DragStartPayload {
	node: OrgNodeData
	clientX: number
	clientY: number
}

const props = defineProps<{
	node: OrgNodeData
	isFocused?: boolean
	isExpanded?: boolean
	hasChildren?: boolean
	disableToggle?: boolean
}>()

const emit = defineEmits<{
	toggle: [id: string]
	focus: [node: OrgNodeData]
	select: [node: OrgNodeData]
	dragStart: [payload: DragStartPayload]
}>()

const dragThreshold = 1
let isPointerDown = false
let startX = 0
let startY = 0
let hasDragged = false

const canToggle = (): boolean => {
	return Boolean(props.hasChildren && !props.disableToggle)
}

const toggleExpand = (e: Event) => {
	if (!canToggle()) {
		return
	}

	e.stopPropagation()
	emit("toggle", props.node.id)
}

const handleFocus = () => {
	emit("focus", props.node)
}

const handleClick = () => {
	if (hasDragged) {
		hasDragged = false
		return
	}

	emit("focus", props.node)
	emit("select", props.node)
}

const handleSelect = () => {
	emit("focus", props.node)
	emit("select", props.node)
}

const handleMouseDown = (e: MouseEvent) => {
	if (e.button !== 0) {
		return
	}

	e.preventDefault()
	isPointerDown = true
	startX = e.clientX
	startY = e.clientY
	hasDragged = false

	e.stopPropagation()
	emit("dragStart", {
		node: props.node,
		clientX: e.clientX,
		clientY: e.clientY,
	})
}

const handleMouseMove = (e: MouseEvent) => {
	if (!isPointerDown || hasDragged) {
		return
	}

	const deltaX = e.clientX - startX
	const deltaY = e.clientY - startY
	if (Math.abs(deltaX) > dragThreshold || Math.abs(deltaY) > dragThreshold) {
		hasDragged = true
	}
}

const handleMouseUp = () => {
	isPointerDown = false
}

const handleMouseLeave = () => {
	isPointerDown = false
}

const handleKeydown = (e: KeyboardEvent) => {
	if (e.key === "Enter") {
		e.preventDefault()
		e.stopPropagation()

		if (canToggle()) {
			toggleExpand(e)
			return
		}

		handleSelect()
	}

	if (e.key === " ") {
		e.preventDefault()
		e.stopPropagation()

		if (canToggle()) {
			toggleExpand(e)
			return
		}

		handleSelect()
	}
}
</script>

<template>
		<div
			class="org-node"
			:class="{ 'is-focused': isFocused, 'is-collapsed': !isExpanded && hasChildren && !disableToggle }"
			role="treeitem"
			:aria-expanded="hasChildren && !disableToggle ? isExpanded : undefined"
			:tabindex="0"
			@click="handleClick"
			@mousedown="handleMouseDown"
			@mousemove="handleMouseMove"
			@mouseup="handleMouseUp"
			@mouseleave="handleMouseLeave"
			@focus="handleFocus"
			@keydown="handleKeydown">
		<div class="org-node-content">
			<div class="org-node-avatar">
				<img
					v-if="node.avatarUrl"
					:src="node.avatarUrl"
					:alt="node.name"
					class="avatar-img" />
				<div v-else class="avatar-placeholder">
					{{ node.name.charAt(0).toUpperCase() }}
				</div>
			</div>

			<div class="org-node-info">
				<div class="org-node-name">{{ node.name }}</div>
				<div class="org-node-title">{{ node.title }}</div>
				<div v-if="node.department" class="org-node-department">
					{{ node.department }}
				</div>
			</div>

				<button
					v-if="hasChildren && !disableToggle"
					class="org-node-toggle"
					:aria-label="isExpanded ? 'Collapse' : 'Expand'"
					@mousedown.stop
					@click="toggleExpand">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					:class="{ 'rotate-180': isExpanded }"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					stroke-width="2"
					stroke-linecap="round"
					stroke-linejoin="round">
					<polyline points="6 9 12 15 18 9"></polyline>
				</svg>
			</button>
		</div>
	</div>
</template>

<style scoped>
.org-node {
	width: 100%;
	height: 100%;
	min-width: 180px;
	background: white;
	border-radius: 8px;
	box-shadow:
		0 1px 3px rgba(0, 0, 0, 0.1),
		0 1px 2px rgba(0, 0, 0, 0.06);
	padding: 12px;
	cursor: pointer;
	transition: all 0.2s ease;
	border: 2px solid transparent;
	box-sizing: border-box;
}

.org-node:hover {
	box-shadow:
		0 4px 6px rgba(0, 0, 0, 0.1),
		0 2px 4px rgba(0, 0, 0, 0.06);
}

.org-node.is-focused {
	border-color: #6366f1;
	outline: none;
}

.org-node.is-collapsed {
	opacity: 0.9;
}

.org-node-content {
	display: flex;
	align-items: center;
	gap: 10px;
	height: 100%;
}

.org-node-avatar {
	flex-shrink: 0;
}

.avatar-img {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	object-fit: cover;
}

.avatar-placeholder {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	background: #e0e7ff;
	color: #4f46e5;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: 600;
	font-size: 16px;
}

.org-node-info {
	flex: 1;
	min-width: 0;
}

.org-node-name {
	font-weight: 600;
	font-size: 14px;
	color: #1f2937;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.org-node-title {
	font-size: 12px;
	color: #6b7280;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.org-node-department {
	font-size: 11px;
	color: #9ca3af;
	margin-top: 2px;
}

.org-node-toggle {
	flex-shrink: 0;
	width: 24px;
	height: 24px;
	border: none;
	background: #f3f4f6;
	border-radius: 4px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s ease;
}

.org-node-toggle:hover {
	background: #e5e7eb;
}

.org-node-toggle svg {
	width: 16px;
	height: 16px;
	color: #6b7280;
	transition: transform 0.2s ease;
}

.org-node-toggle svg.rotate-180 {
	transform: rotate(180deg);
}

@media (max-width: 768px) {
	.org-node {
		min-width: 150px;
		padding: 10px;
	}
}
</style>
