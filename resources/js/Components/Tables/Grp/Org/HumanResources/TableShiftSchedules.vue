<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import HrConfirmDialog from "@/Components/HumanResources/HrConfirmDialog.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faPencil, faTrashAlt)

interface ScheduleDay {
	day_of_week: number
	is_working_day: boolean
	start_time: string | null
	end_time: string | null
}

interface DayGroup {
	from: number
	to: number
	hours: string | null
}

defineProps<{
	data: object
	tab?: string
}>()

const confirmGroup = "shift-schedule-delete"
const confirm = useConfirm()
const deletingId = ref<number | null>(null)

const shortDayNames = [
	ctrans("Mon"),
	ctrans("Tue"),
	ctrans("Wed"),
	ctrans("Thu"),
	ctrans("Fri"),
	ctrans("Sat"),
	ctrans("Sun"),
]

const formatTime = (time: string) => time.substring(0, 5)

const groupDays = (days: ScheduleDay[] | undefined): DayGroup[] => {
	if (!days?.length) return []

	const groups: DayGroup[] = []

	for (let dayOfWeek = 1; dayOfWeek <= 7; dayOfWeek++) {
		const day = days.find((scheduleDay) => Number(scheduleDay.day_of_week) === dayOfWeek)
		const hours =
			day?.is_working_day && day.start_time && day.end_time
				? `${formatTime(day.start_time)}–${formatTime(day.end_time)}`
				: null
		const previous = groups[groups.length - 1]

		if (previous && previous.hours === hours) {
			previous.to = dayOfWeek
		} else {
			groups.push({ from: dayOfWeek, to: dayOfWeek, hours })
		}
	}

	return groups
}

const dayRange = (group: DayGroup) =>
	group.from === group.to
		? shortDayNames[group.from - 1]
		: `${shortDayNames[group.from - 1]}–${shortDayNames[group.to - 1]}`

const confirmDelete = (schedule: { id: number; name: string }) => {
	confirm.require({
		group: confirmGroup,
		header: ctrans("Delete shift schedule"),
		message: ctrans("Are you sure you want to delete :name? This action cannot be undone.", { name: schedule.name }),
		rejectProps: { label: ctrans("Cancel") },
		acceptProps: { label: ctrans("Delete"), severity: "danger" },
		accept: () => {
			router.delete(route("grp.org.hr.shift_schedules.delete", [route().params.organisation, schedule.id]), {
				preserveScroll: true,
				onStart: () => {
					deletingId.value = schedule.id
				},
				onFinish: () => {
					deletingId.value = null
				},
			})
		},
	})
}

const pillClass = "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
</script>

<template>
	<div>
		<Table :resource="data" :name="tab" class="mt-5">
			<template #cell(name)="{ item }">
				<span>{{ item.name }}</span>
			</template>

			<template #cell(type)="{ item }">
				<span :class="[pillClass, item.type === 'shift' ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-700']">
					{{ item.type === "shift" ? ctrans("Shift") : ctrans("Default") }}
				</span>
			</template>

			<template #cell(is_active)="{ item }">
				<span :class="[pillClass, item.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
					{{ item.is_active ? ctrans("Active") : ctrans("Inactive") }}
				</span>
			</template>

			<template #cell(time)="{ item }">
				<div v-if="groupDays(item.days).length" class="space-y-0.5 text-xs">
					<div v-for="group in groupDays(item.days)" :key="group.from" class="flex items-baseline gap-2 whitespace-nowrap">
						<span class="w-16 shrink-0 text-gray-500">{{ dayRange(group) }}</span>
						<span v-if="group.hours" class="font-medium tabular-nums text-gray-900">{{ group.hours }}</span>
						<span v-else class="text-gray-400">{{ ctrans("Day off") }}</span>
					</div>
				</div>
				<span v-else class="text-xs text-gray-400">{{ ctrans("No hours set") }}</span>
			</template>

			<template #cell(actions)="{ item }">
				<div class="flex gap-2">
					<Link :href="route('grp.org.hr.shift_schedules.edit', [route().params.organisation, item.id])">
						<Button type="tertiary" size="xs" :label="ctrans('Edit')">
							<template #icon>
								<FontAwesomeIcon icon="fal fa-pencil" class="text-[--app-accent]" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</Link>
					<Button type="tertiary" size="xs" :label="ctrans('Delete')" :loading="deletingId === item.id" @click="confirmDelete(item)">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Table>

		<HrConfirmDialog :group="confirmGroup" />
	</div>
</template>
