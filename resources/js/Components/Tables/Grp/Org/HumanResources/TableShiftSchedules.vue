<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
	data: object
	tab?: string
}>()

const getDayName = (dayOfWeek: number) => {
	const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]
	return days[dayOfWeek - 1] || ""
}

const formatTime = (time: string | null) => {
	if (!time) return "--:--"
	return time.substring(0, 5)
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(name)="{ item }">
			<span>{{ item.name }}</span>
		</template>

		<template #cell(type)="{ item }">
			<span
				class="px-2 py-1 rounded text-xs font-medium"
				:class="
					item.type === 'shift'
						? 'bg-purple-100 text-purple-800'
						: 'bg-gray-100 text-gray-800'
				">
				{{ item.type === "shift" ? "Shift" : "Default" }}
			</span>
		</template>

		<template #cell(is_active)="{ item }">
			<span
				class="px-2 py-1 rounded text-xs font-medium"
				:class="
					item.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
				">
				{{ item.is_active ? "Active" : "Inactive" }}
			</span>
		</template>

		<template #cell(time)="{ item }">
			<div class="text-xs space-y-1">
				<div v-for="day in item.days" :key="day.id" class="flex justify-between gap-2">
					<span class="text-gray-600 font-medium w-16">
						{{ getDayName(day.day_of_week) }}:
					</span>
					<span v-if="day.is_working_day" class="font-medium">
						{{ formatTime(day.start_time) }} - {{ formatTime(day.end_time) }}
					</span>
					<span v-else class="text-gray-400 italic"> Off </span>
				</div>
			</div>
		</template>

		<template #cell(actions)="{ item }">
			<div class="flex gap-2">
				<Link
					:href="
						route('grp.org.hr.shift_schedules.edit', [
							route().params.organisation,
							item.id,
						])
					">
					<Button label="Edit Hours" type="secondary" size="sm" />
				</Link>
				<ModalConfirmationDelete
					:title="trans('Delete Shift Schedule')"
					:description="
						trans(
							'Are you sure you want to delete :name? This action cannot be undone.',
							{
								name: item.name,
							}
						)
					"
					:routeDelete="{
						name: 'grp.org.hr.shift_schedules.delete',
						parameters: [route().params.organisation, item.id],
					}">
					<template #default="{ isOpenModal, changeModel, isLoadingdelete }">
						<Button
							:loading="isLoadingdelete"
							@click="changeModel"
							label="Delete"
							type="delete"
							size="sm" />
					</template>
				</ModalConfirmationDelete>
			</div>
		</template>
	</Table>
</template>
