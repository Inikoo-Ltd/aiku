<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"

const props = defineProps<{
	show: boolean
	task: any | null
	employees: { value: number; label: string }[]
	statuses: Record<string, string>
}>()

const emit = defineEmits<{
	(e: "close"): void
}>()

const form = useForm<{
	title: string
	description: string | null
	status: string
	assignee_id: number | null
}>({
	title: "",
	description: null,
	status: "pending",
	assignee_id: null,
})

watch(
	() => props.task,
	(task) => {
		form.clearErrors()
		if (task) {
			form.title = task.title
			form.description = task.description ?? null
			form.status = task.status ?? "pending"
			form.assignee_id = task.assignee_id ?? null
		} else {
			form.reset()
		}
	},
	{ immediate: true }
)

const modalTitle = computed(() => (props.task ? trans("Edit Task") : trans("New Task")))

const submit = () => {
	if (props.task) {
		form.put(route("grp.workspace.tasks.update", props.task.id), {
			preserveScroll: true,
			onSuccess: () => emit("close"),
		})
	} else {
		form.post(route("grp.workspace.tasks.store"), {
			preserveScroll: true,
			onSuccess: () => emit("close"),
		})
	}
}
</script>

<template>
	<Modal :isOpen="show" @onClose="emit('close')" width="w-full max-w-lg">
		<h2 class="text-lg font-semibold text-gray-800 mb-4">{{ modalTitle }}</h2>

		<form class="space-y-4" @submit.prevent="submit">
			<div>
				<label class="block text-sm font-medium text-gray-700">{{ trans("Title") }}</label>
				<input
					v-model="form.title"
					type="text"
					required
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
				<div v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</div>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700">{{ trans("Description") }}</label>
				<textarea
					v-model="form.description"
					rows="3"
					class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
				<div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700">{{ trans("Assignee") }}</label>
					<PureMultiselect
						v-model="form.assignee_id"
						:options="employees"
						:valueProp="'value'"
						:label="'label'"
						:searchable="true"
						:placeholder="trans('Select employee...')"
						@update:modelValue="() => (form.errors.assignee_id = null)" />
					<div v-if="form.errors.assignee_id" class="mt-1 text-sm text-red-600">{{ form.errors.assignee_id }}</div>
				</div>

				<div v-if="task">
					<label class="block text-sm font-medium text-gray-700">{{ trans("Status") }}</label>
					<select
						v-model="form.status"
						class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:border-indigo-500 focus:ring-indigo-500">
						<option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
					</select>
				</div>
			</div>

			<div class="mt-6 flex justify-end gap-2">
				<Button type="tertiary" @click="emit('close')">{{ trans("Cancel") }}</Button>
				<Button type="save" nativeType="submit" :loading="form.processing">{{ trans("Save") }}</Button>
			</div>
		</form>
	</Modal>
</template>
