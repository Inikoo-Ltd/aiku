<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableShiftSchedules from "@/Components/Tables/Grp/Org/HumanResources/TableShiftSchedules.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
	pageHead: {}
	title: string
	data: {}
}>()

const isModalOpen = ref(false)
const form = ref({
	name: "",
})
const isSubmitting = ref(false)

const openCreateModal = () => {
	form.value = { name: "" }
	isModalOpen.value = true
}

const submitForm = async () => {
	if (!form.value.name.trim()) return

	isSubmitting.value = true
	try {
		await axios.post(route("grp.org.hr.shift_schedules.store", route().params.organisation), {
			name: form.value.name,
			type: "shift",
		})
		isModalOpen.value = false
		notify({
			title: trans("Success"),
			text: trans("Done, shift added successfully"),
			type: "success",
		})
		router.reload()
	} catch (error: any) {
		console.error("Failed to create shift schedule:", error)
		notify({
			title: trans("Error"),
			text: error.response?.data?.message || trans("Failed to create shift schedule"),
			type: "error",
		})
	} finally {
		isSubmitting.value = false
	}
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-btn-create="{ action }">
			<Button
				@click="openCreateModal"
				type="create"
				icon="fal fa-plus"
				:label="trans('Create Shift')"
				capitalize />
		</template>
	</PageHeading>

	<TableShiftSchedules :data="data" />

	<Dialog v-model:visible="isModalOpen" modal :style="{ width: '500px' }" :closable="false">
		<div class="p-6">
			<h2 class="text-xl font-semibold mb-4">Create Shift Schedule</h2>

			<div class="mb-4">
				<label class="block text-sm font-medium text-gray-700 mb-1">Shift Name</label>
				<InputText
					v-model="form.name"
					placeholder="e.g., Morning Shift, Evening Shift, Night Shift"
					class="w-full" />
			</div>

			<div class="mb-4 p-3 bg-gray-50 rounded border">
				<p class="text-sm text-gray-600">
					After creating, you can edit the shift to set working hours for each day.
				</p>
			</div>

			<div class="flex justify-end gap-2 mt-6">
				<Button :label="trans('Cancel')" type="exit" @click="isModalOpen = false" />
				<Button
					:label="isSubmitting ? trans('Creating...') : trans('Create')"
					type="primary"
					:disabled="isSubmitting || !form.name.trim()"
					:loading="isSubmitting"
					@click="submitForm" />
			</div>
		</div>
	</Dialog>
</template>
