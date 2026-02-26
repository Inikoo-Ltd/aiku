<script setup lang="ts">
import { computed } from "vue"

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

interface EmployeePayload {
	group: OrgNodeData
	employee: OrgNodeData
}

const props = defineProps<{
	node: OrgNodeData
	isFocused?: boolean
	focusedEmployeeId?: string | null
}>()

const emit = defineEmits<{
	focus: [node: OrgNodeData]
	select: [node: OrgNodeData]
	employeeFocus: [payload: EmployeePayload]
	employeeSelect: [payload: EmployeePayload]
	dragStart: [payload: DragStartPayload]
}>()

const employeeLimit = 10
const dragThreshold = 1
let isPointerDown = false
let startX = 0
let startY = 0
let hasDragged = false

const limitedEmployees = computed(() => {
	return (props.node.reports ?? []).slice(0, employeeLimit)
})

const emitGroupFocus = () => {
	emit("focus", props.node)
}

const handleGroupClick = () => {
	if (hasDragged) {
		hasDragged = false
		return
	}

	emit("focus", props.node)
	emit("select", props.node)
}

const emitEmployeeFocus = (employee: OrgNodeData) => {
	emit("employeeFocus", {
		group: props.node,
		employee,
	})
}

const emitEmployeeSelect = (employee: OrgNodeData) => {
	emit("employeeSelect", {
		group: props.node,
		employee,
	})
}

const handleEmployeeClick = (employee: OrgNodeData) => {
	if (hasDragged) {
		hasDragged = false
		return
	}

	emitEmployeeFocus(employee)
	emitEmployeeSelect(employee)
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

const handleGroupKeydown = (e: KeyboardEvent) => {
	if (e.key !== "Enter" && e.key !== " ") {
		return
	}

	e.preventDefault()
	e.stopPropagation()
	handleGroupClick()
}

const handleEmployeeKeydown = (e: KeyboardEvent, employee: OrgNodeData) => {
	if (e.key !== "Enter" && e.key !== " ") {
		return
	}

	e.preventDefault()
	e.stopPropagation()
	handleEmployeeClick(employee)
}
</script>

<template>
	<div
		class="org-node"
		:class="{ 'is-focused': isFocused }"
		role="treeitem"
		:tabindex="0"
		@click.self="handleGroupClick"
		@mousedown="handleMouseDown"
		@mousemove="handleMouseMove"
		@mouseup="handleMouseUp"
		@mouseleave="handleMouseLeave"
		@focus="emitGroupFocus"
		@keydown="handleGroupKeydown">
		<div class="org-header" @click.stop="handleGroupClick">
			<div class="org-title">{{ node.name }}</div>
			<div class="org-subtitle">{{ node.title || "Job Position" }}</div>
		</div>

		<div class="org-connector" aria-hidden="true"></div>

		<div class="employees-grid">
			<div
				v-for="(employee, index) in limitedEmployees"
				:key="employee.id || index"
				class="employee-card"
				:class="{ 'is-focused': focusedEmployeeId === employee.id }"
				:title="employee.name"
				role="button"
				:tabindex="0"
				@focus="emitEmployeeFocus(employee)"
				@click.stop="handleEmployeeClick(employee)"
				@keydown="handleEmployeeKeydown($event, employee)">
				<img
					v-if="employee.avatarUrl"
					:src="employee.avatarUrl"
					:alt="employee.name"
					class="employee-avatar" />
				<div v-else class="employee-avatar-placeholder">
					{{ employee.name.charAt(0).toUpperCase() }}
				</div>

				<div class="employee-info">
					<div class="employee-name">{{ employee.name }}</div>
					<div class="employee-role">{{ employee.title || "Employee" }}</div>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.org-node {
	width: 100%;
	height: 100%;
	background: white;
	border-radius: 10px;
	box-shadow:
		0 1px 3px rgba(0, 0, 0, 0.1),
		0 1px 2px rgba(0, 0, 0, 0.06);
	padding: 14px;
	border: 2px solid transparent;
	box-sizing: border-box;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.org-node.is-focused {
	border-color: #6366f1;
}

.org-node:hover {
	box-shadow:
		0 4px 6px rgba(0, 0, 0, 0.1),
		0 2px 4px rgba(0, 0, 0, 0.06);
}

.org-header {
	text-align: center;
}

.org-title {
	font-size: 15px;
	font-weight: 700;
	color: #111827;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.org-subtitle {
	margin-top: 4px;
	font-size: 12px;
	color: #6b7280;
}

.org-connector {
	width: 2px;
	height: 14px;
	margin: 10px auto 12px;
	background: #cbd5e1;
	border-radius: 999px;
}

.employees-grid {
	display: grid;
	gap: 10px;
}

.employee-card {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px;
	background: #f9fafb;
	border: 1px solid #e5e7eb;
	border-radius: 7px;
	cursor: pointer;
	transition: border-color 0.15s ease, background-color 0.15s ease;
}

.employee-card:hover {
	background: #f3f4f6;
	border-color: #d1d5db;
}

.employee-card.is-focused {
	border-color: #6366f1;
	background: #eef2ff;
}

.employee-avatar,
.employee-avatar-placeholder {
	width: 34px;
	height: 34px;
	flex-shrink: 0;
	border-radius: 50%;
}

.employee-avatar {
	object-fit: cover;
}

.employee-avatar-placeholder {
	background: #e0e7ff;
	color: #4f46e5;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 13px;
	font-weight: 700;
}

.employee-info {
	min-width: 0;
}

.employee-name {
	font-size: 13px;
	font-weight: 600;
	color: #1f2937;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.employee-role {
	font-size: 11px;
	color: #6b7280;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

@media (max-width: 599px) {
	.employees-grid {
		grid-template-columns: repeat(1, minmax(0, 1fr));
	}
}

@media (min-width: 600px) and (max-width: 1023px) {
	.employees-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (min-width: 1024px) {
	.employees-grid {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}
</style>
