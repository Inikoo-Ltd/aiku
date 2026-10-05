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
import { faEye, faEyeSlash, faGripVertical, faChevronDown, faPlus } from "@fal"
import { faCopy } from "@far"
import { faTrash } from "@fas"
import { routeType } from "@/types/route"
import { ctrans } from "@/Composables/useTrans"


defineProps<{
	uploadImageRoute : routeType
}>()

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
	return dataModel.value?.data?.fieldValue?.navigation ?? []
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

const setMenuActive = (index: number) => {
	selectedMenu.value = index
	visibleDrawer.value = true
}

const addNavigation = () => {
	updateNavigation(list => {
		list.push({
			id: ulid(),
			label: ctrans("New Navigation"),
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

const confirmDeleteNavigation = (event: Event, index: number) => {
	confirm.require({
		group: "menu-navigation",
		target: event.currentTarget as HTMLElement,
		message: ctrans("Delete this navigation?"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true, size: "small" },
		acceptProps: { label: ctrans("Delete"), severity: "danger", size: "small" },
		accept: () => deleteNavigation(index),
	})
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
	<div class="flex items-center justify-between px-3 py-2">
		<div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
			{{ ctrans("Navigation") }}
			<span class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-[10px] text-gray-600 tabular-nums">{{ navigation.length }}</span>
		</div>
		<Button :label="ctrans('Add')" :icon="faPlus" type="tertiary" size="xxs" @click="addNavigation" />
	</div>

	<draggable
		v-model="navigation"
		item-key="id"
		class="px-2 pb-2 space-y-1"
		ghost-class="ghost"
		:fallbackOnBody="true"
		:move="allowMove"
		@update:modelValue="onDragUpdate"
	>
		<template #item="{ element, index }">
			<div
				@click="setMenuActive(index)"
				class="group flex h-8 items-center rounded border bg-white text-sm cursor-pointer transition-colors"
				:class="[
					visibleDrawer && selectedMenu === index ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-indigo-300',
					element.hidden ? 'bg-gray-50 text-gray-400' : 'text-gray-700',
				]"
			>
				<div class="drag-handle flex h-full w-6 shrink-0 items-center justify-center cursor-grab text-gray-400 hover:text-gray-700"
					@click.stop>
					<FontAwesomeIcon :icon="faGripVertical" class="text-xs" fixed-width aria-hidden="true" />
				</div>

				<span class="flex-1 min-w-0 truncate" :class="element.hidden ? 'line-through' : ''">
					{{ element.label }}
				</span>

				<FontAwesomeIcon v-if="element.type === 'multiple'" :icon="faChevronDown"
					class="mx-1 shrink-0 text-[10px] text-gray-400" v-tooltip="ctrans('Has sub navigation')" fixed-width />

				<div class="flex shrink-0 items-center pr-1">
					<button type="button" @click.stop="toggleHiddenNavigation(index)"
						class="h-6 w-6 rounded hover:bg-gray-100"
						:class="element.hidden ? 'text-gray-600' : 'text-gray-400 opacity-0 group-hover:opacity-100'"
						v-tooltip="element.hidden ? ctrans('Show') : ctrans('Hide')">
						<FontAwesomeIcon :icon="element.hidden ? faEyeSlash : faEye" class="text-xs" fixed-width />
					</button>
					<button type="button" @click.stop="duplicateNavigation(index)"
						class="h-6 w-6 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700 opacity-0 group-hover:opacity-100"
						v-tooltip="ctrans('Duplicate')">
						<FontAwesomeIcon :icon="faCopy" class="text-xs" fixed-width />
					</button>
					<button type="button" @click.stop="(event) => confirmDeleteNavigation(event, index)"
						class="h-6 w-6 rounded text-red-400 hover:bg-red-50 hover:text-red-600 opacity-0 group-hover:opacity-100"
						v-tooltip="ctrans('Delete')">
						<FontAwesomeIcon :icon="faTrash" class="text-xs" fixed-width />
					</button>
				</div>
			</div>
		</template>
	</draggable>

	<div v-if="!navigation.length" class="mx-2 mb-2 rounded border border-dashed border-gray-300 py-6 text-center text-xs text-gray-500">
		{{ ctrans("No navigation yet") }}
	</div>

	<Drawer
		v-model:visible="visibleDrawer"
		:header="selectedMenu !== null ? navigation[selectedMenu]?.label : ''"
		position="right"
		:pt="{ root: { style: 'width: min(34rem, 92vw)' }, content: { class: '!px-4 !pb-4' } }"
	>
		<EditMode
			v-if="selectedMenu !== null"
			:model-value="navigation[selectedMenu]"
			@update:model-value="updateNavigationFromDrawer"
			:uploadImageRoute
		/>
	</Drawer>

	<ConfirmPopup group="menu-navigation" />
</template>

<style scoped lang="scss">
.ghost {
	opacity: 0.5;
	background-color: #e2e8f0;
	border: 1px dashed #4f46e5;
}
</style>
