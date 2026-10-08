<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tag from "@/Components/Tag.vue"
import Select from "primevue/select"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserFriends, faUserTimes, faEdit, faPlus } from "@fal"

library.add(faUserFriends, faUserTimes, faEdit, faPlus)

interface LeaveCover {
	id: number
	employee_id: number
	employee_name: string
	type_label: string
	start_date: string
	end_date: string
	is_ongoing: boolean
	cover_employee_id: number | null
	covered_by: string | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: object
	can_edit: boolean
	employee_options: Record<string, string>
}>()

const locale = useLocaleStore()
const selectedLeave = ref<LeaveCover | null>(null)

const coverOptions = computed(() =>
	Object.entries(props.employee_options ?? {})
		.map(([value, label]) => ({ value: Number(value), label }))
		.filter((option) => option.value !== selectedLeave.value?.employee_id)
)

const coverForm = useForm({
	cover_employee_id: "" as string | number,
})

const formatDate = (date: string) => useFormatTime(date, { localeCode: locale?.language?.code })

const openCoverModal = (leave: LeaveCover) => {
	selectedLeave.value = leave
	coverForm.clearErrors()
	coverForm.cover_employee_id = leave.cover_employee_id ?? ""
}

const closeCoverModal = () => {
	selectedLeave.value = null
}

const saveCover = (leave: LeaveCover, coverEmployeeId: string | number) => {
	coverForm
		.transform((data) => ({ ...data, cover_employee_id: coverEmployeeId }))
		.patch(route("grp.org.hr.leaves.cover.update", { organisation: route().params.organisation, leave: leave.id }), {
			preserveScroll: true,
			onSuccess: closeCoverModal,
		})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<Table :resource="data" class="mt-5">
		<template #cell(start_date)="{ item: leave }">
			<span class="whitespace-nowrap">{{ formatDate(leave.start_date) }}</span>
			<Tag v-if="leave.is_ongoing" :label="ctrans('Now')" :theme="3" size="xs" class="ml-1" />
		</template>

		<template #cell(end_date)="{ item: leave }">
			<span class="whitespace-nowrap">{{ formatDate(leave.end_date) }}</span>
		</template>

		<template #cell(covered_by)="{ item: leave }">
			<span v-if="leave.covered_by">{{ leave.covered_by }}</span>
			<span v-else class="text-amber-600">{{ ctrans("Not covered") }}</span>
		</template>

		<template #cell(actions)="{ item: leave }">
			<div v-if="can_edit" class="flex justify-end gap-2">
				<Button
					:icon="leave.covered_by ? faEdit : faPlus"
					:label="leave.covered_by ? ctrans('Change') : ctrans('Assign cover')"
					type="tertiary"
					size="xs"
					@click="openCoverModal(leave)" />
				<Button
					v-if="leave.covered_by"
					:icon="faUserTimes"
					:label="ctrans('End cover')"
					type="negative"
					size="xs"
					:disabled="coverForm.processing"
					@click="saveCover(leave, '')" />
			</div>
		</template>
	</Table>

	<Modal :isOpen="!!selectedLeave" @onClose="closeCoverModal" width="w-full max-w-lg">
		<form v-if="selectedLeave" class="space-y-4" @submit.prevent="saveCover(selectedLeave, coverForm.cover_employee_id)">
			<div>
				<h2 class="text-lg font-semibold text-gray-800">{{ ctrans("Cover for :name", { name: selectedLeave.employee_name }) }}</h2>
				<p class="text-sm text-gray-500">{{ formatDate(selectedLeave.start_date) }} – {{ formatDate(selectedLeave.end_date) }}</p>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700">{{ ctrans("Covered by") }}</label>
				<Select
					v-model="coverForm.cover_employee_id"
					:options="coverOptions"
					optionLabel="label"
					optionValue="value"
					filter
					:placeholder="ctrans('Select employee')"
					class="mt-1 w-full" />
				<p v-if="coverForm.errors.cover_employee_id" class="mt-1 text-sm text-red-600">{{ coverForm.errors.cover_employee_id }}</p>
			</div>

			<p class="text-sm text-gray-500">{{ ctrans("The cover gets this employee's permissions until the leave ends") }}</p>

			<div class="flex justify-end gap-2">
				<Button :label="ctrans('Cancel')" type="tertiary" @click="closeCoverModal" />
				<Button type="save" nativeType="submit" :label="ctrans('Save')" :loading="coverForm.processing" :disabled="!coverForm.cover_employee_id" />
			</div>
		</form>
	</Modal>
</template>
