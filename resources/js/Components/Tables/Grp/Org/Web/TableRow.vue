<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ModalWebpage from "@/Components/Utils/ModalWebpage.vue"
import axios from "axios"

// Define component name
defineOptions({
	name: "TableRow",
})

// Define the TreeNode interface.
interface TreeNode {
	id: number | string
	name: string
	value?: string
	status?: string
	children?: TreeNode[]
}

const props = defineProps<{
	node: TreeNode
	level?: number
}>()

const { node, level = 0 } = props

// Local state for expansion and loading.
const isExpanded = ref(false)
const isLoading = ref(false)
const childrenData = ref<TreeNode[]>(node.children || [])

// Dummy data for demonstration purposes.
const dummyChildrenData: Record<string, TreeNode[]> = {
	"1": [
		{ id: "11", name: "Child A1", value: "50", status: "Active" },
		{ id: "12", name: "Child A2", value: "30", status: "Inactive" },
	],
	"11": [
		{ id: "111", name: "Child of A1 1", value: "10", status: "Pending" },
		{ id: "112", name: "Child of A1 2", value: "20", status: "Active" },
	],
	"12": [
		{ id: "121", name: "Child of A2 1", value: "15", status: "Active" },
		{ id: "122", name: "Child of A2 2", value: "25", status: "Inactive" },
	],
	"2": [{ id: "21", name: "Child B1", value: "40", status: "Active" }],
	"21": [
		{ id: "211", name: "Child of B1 1", value: "5", status: "Pending" },
		{ id: "212", name: "Child of B1 2", value: "10", status: "Active" },
	],
}

const fetchChildrenData = async () => {
	isLoading.value = true
	// Simulate an API call delay.
	setTimeout(() => {
		childrenData.value = dummyChildrenData[node.id] || []
		isLoading.value = false
	}, 1000)
}

const toggle = () => {
	if (!isExpanded.value && childrenData.value.length === 0) {
		fetchChildrenData() // Load dummy data when expanding.
	}
	isExpanded.value = !isExpanded.value
}

const showModal = ref(false)
const openModal = () => {
	showModal.value = true
}
const closeModal = () => {
	showModal.value = false
}
</script>

<template>
	<!-- Main Table Row -->
	<tr class="hover:bg-gray-50 transition-colors duration-200 border-b border-gray-200">
		<td class="px-4 py-2 whitespace-nowrap">
			<div class="flex items-center">
				<!-- Indented section for draggable icon and node name -->
				<div :style="{ paddingLeft: `${level * 1.5}rem` }" class="flex items-center">
					<FontAwesomeIcon
						icon="fal fa-grip-lines"
						class="cursor-move text-gray-500 hover:text-gray-700 transition-colors duration-150 mr-2" />
					<span class="font-semibold text-gray-900">{{ node.name }}</span>
				</div>

				<div class="flex items-center ml-1">
					<button
						@click="toggle"
						title="Expand / Collapse"
						class="p-2 text-gray-600 hover:text-gray-800 focus:outline-hidden transition-colors duration-150">
						<FontAwesomeIcon v-if="!isExpanded" icon="fal fa-chevron-right" size="sm" />
						<FontAwesomeIcon v-else icon="fal fa-chevron-down" size="sm" />
					</button>
					<button
						@click="openModal"
						title="Add New Webpage"
						class="ml-2 text-blue-500 hover:text-blue-600 focus:outline-hidden transition-colors duration-150">
						<FontAwesomeIcon icon="fal fa-plus" size="sm" />
					</button>
				</div>
			</div>
		</td>
	</tr>

	<!-- Expanded Child Rows -->
	<template v-if="isExpanded">
		<tr v-if="isLoading">
			<td
				class="px-4 py-2 whitespace-nowrap text-center text-blue-500 font-semibold"
				colspan="1">
				Loading...
			</td>
		</tr>
		<TableRow v-for="child in childrenData" :key="child.id" :node="child" :level="level + 1" />
	</template>

	<!-- Modal Component -->
	<ModalWebpage
		:isOpen="showModal"
		:title="{ label: 'Webpage', information: 'Add new webpage' }"
		@close="closeModal" />
</template>
