<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { ref } from "vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import NoteModal from "@/Components/Workspace/NoteModal.vue"

defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: any
}>()

const isModalOpen = ref(false)
const editingNote = ref<any>(null)

const openModal = (note: any = null) => {
	editingNote.value = note
	isModalOpen.value = true
}

const closeModal = () => {
	isModalOpen.value = false
	editingNote.value = null
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-note="{ action }">
			<Button :icon="action.icon" :label="action.label" :style="action.style" @click="openModal()" />
		</template>
	</PageHeading>

	<Table :resource="data" class="mt-5">
		<template #cell(title)="{ item }">
			<span class="font-medium text-gray-900">{{ item.title }}</span>
		</template>

		<template #cell(content)="{ item }">
			<p class="text-sm text-gray-600 whitespace-pre-wrap line-clamp-2">{{ item.content }}</p>
		</template>

		<template #cell(actions)="{ item }">
			<div class="flex items-center justify-end gap-2">
				<Button type="tertiary" icon="fal fa-pencil" size="xs" v-tooltip="trans('Edit note')" @click="openModal(item)" />
				<ModalConfirmationDelete
					:routeDelete="{ name: 'grp.workspace.notes.destroy', parameters: { note: item.id } }"
					:title="trans('Are you sure you want to delete this note?')"
					:noLabel="trans('Delete')"
					noIcon="fal fa-trash">
					<template #default="{ changeModel }">
						<Button type="negative" icon="fal fa-trash" size="xs" v-tooltip="trans('Delete note')" @click="changeModel()" />
					</template>
				</ModalConfirmationDelete>
			</div>
		</template>
	</Table>

	<NoteModal :show="isModalOpen" :note="editingNote" @close="closeModal" />
</template>
