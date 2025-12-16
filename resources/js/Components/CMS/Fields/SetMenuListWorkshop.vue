<script setup lang="ts">
import { ref, computed } from "vue"
import draggable from "vuedraggable"
import Drawer from "primevue/drawer"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"

import cloneDeep from "lodash-es/cloneDeep"
import { ulid } from "ulid"

import Button from "@/Components/Elements/Buttons/Button.vue"
import EditMode from "../Website/Menus/EditMode/EditMode.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEye, faEyeSlash } from "@fal"
import { faCopy } from "@far"
import { faTrash } from "@fas"

const dataModel = defineModel<{
	data: {
		component: string
		fieldValue: {
			navigation: any[]
		}
	}
}>("data")

const confirm = useConfirm()
const visibleDrawer = ref(false)
const selectedMenu = ref<number | null>(null)


const navigation = computed(() => {
	return dataModel.value?.data.fieldValue.navigation ?? []
})


const updateNavigation = (updater: (list: any[]) => any[] | void) => {
	if (!dataModel.value) return

	const nextNavigation = cloneDeep(
		dataModel.value.data.fieldValue.navigation
	)

	const result = updater(nextNavigation)

	dataModel.value = {
		...dataModel.value,
		data: {
			...dataModel.value.data,
			fieldValue: {
				...dataModel.value.data.fieldValue,
				navigation: result ?? nextNavigation,
			},
		},
	}
}


const allowMove = (evt: any) =>
	evt.originalEvent?.target?.closest(".drag-handle") !== null

const onDragUpdate = (newList: any[]) => {
	updateNavigation(() => newList)
}

/* --------------------------------------------
 * Actions
 * -------------------------------------------- */
const setMenuActive = (index: number) => {
	selectedMenu.value = index
	visibleDrawer.value = true
}

const addNavigation = () => {
	updateNavigation(list => {
		list.push({
			id: ulid(),
			label: "New Navigation",
			type: "single",
			hidden: false,
		})
	})
}

const deleteNavigation = (index: number) => {
	updateNavigation(list => {
		list.splice(index, 1)
	})

	if (selectedMenu.value === index) {
		selectedMenu.value = null
		visibleDrawer.value = false
	}
}

const toggleHiddenNavigation = (index: number) => {
	updateNavigation(list => {
		if (list[index]) {
			list[index].hidden = !list[index].hidden
		}
	})
}

const duplicateNavigation = (index: number) => {
	updateNavigation(list => {
		const nav = list[index]
		if (!nav) return
		list.splice(index + 1, 0, cloneDeep(nav))
	})
}

const updateNavigationFromDrawer = (value: any) => {
	if (selectedMenu.value === null) return

	updateNavigation(list => {
		list[selectedMenu.value!] = cloneDeep(value)
	})
}
</script>

<template>
	<div class="flex justify-end m-2">
		<Button
			label="Add Navigation"
			type="create"
			size="xs"
			@click="addNavigation"
		/>
	</div>

	<draggable
		:list="navigation"
		item-key="id"
		class="space-y-2"
		ghost-class="ghost"
		chosen-class="chosen"
		drag-class="dragging"
		:fallbackOnBody="true"
		:move="allowMove"
		@update="onDragUpdate"
	>
		<template #item="{ element, index }">
			<div
				@click="setMenuActive(index)"
				class="group flex items-center bg-white border border-gray-200 rounded shadow-sm overflow-hidden cursor-pointer transition hover:ring-2 hover:ring-indigo-400"
				:class="element.hidden ? 'opacity-50 hover:opacity-100' : ''"
			>
				<div
					class="drag-handle cursor-move px-3 py-2 text-gray-500 hover:text-indigo-600"
					@click.stop
				>
					≡
				</div>

				<div class="flex-1 px-4 py-2">
					<div class="text-sm font-semibold text-gray-700">
						{{ element.label }}
					</div>
				</div>

				<button
					@click.stop="toggleHiddenNavigation(index)"
					class="px-2 py-1"
				>
					<FontAwesomeIcon
						:icon="element.hidden ? faEyeSlash : faEye"
						class="text-gray-400 hover:text-gray-700"
					/>
				</button>

				<button
					@click.stop="duplicateNavigation(index)"
					class="px-2 py-1"
				>
					<FontAwesomeIcon
						:icon="faCopy"
						class="text-gray-400 hover:text-gray-700"
					/>
				</button>

				<button
					@click.stop="deleteNavigation(index)"
					class="px-2 py-1"
				>
					<FontAwesomeIcon
						:icon="faTrash"
						class="text-red-400 hover:text-red-700"
					/>
				</button>
			</div>
		</template>
	</draggable>

	<Drawer
		v-model:visible="visibleDrawer"
		:header="selectedMenu !== null ? navigation[selectedMenu]?.label : ''"
		position="right"
		:pt="{ root: { style: 'width: 40vw' } }"
	>
		<EditMode
			v-if="selectedMenu !== null"
			:model-value="navigation[selectedMenu]"
			@update:model-value="updateNavigationFromDrawer"
		/>
	</Drawer>

	<ConfirmPopup />
</template>

<style scoped lang="scss">
.ghost {
	opacity: 0.5;
	background-color: #e2e8f0;
	border: 2px dashed #4f46e5;
}
</style>
