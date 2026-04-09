<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 08 Sept 2022 00:38:38 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TabsRightActions from "@/Components/Navigation/TabsRightActions.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TableTimeTrackers from "@/Components/Tables/Grp/Org/HumanResources/TableTimeTrackers.vue"
import TableClockings from "@/Components/Tables/Grp/Org/HumanResources/TableClockings.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVoteYea, faArrowsH, faPlus } from "@fal"
import { format, parseISO } from "date-fns"
import { useSecondsToMS, useHMAP } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import type { routeType } from "@/types/route"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import ManualClockOutModal from "@/Components/HumanResources/ManualClockOutModal.vue"

library.add(faVoteYea, faArrowsH, faPlus)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: Navigation
	}
	history?: {}
	override?: {}
	time_trackers?: {}
	clockings?: {}
	manual_clock_out?: {
		can_edit: boolean
		is_today: boolean
		has_open_tracker: boolean
		route?: routeType | null
	}
	timesheet: {
		work_start_at?: string
		work_end_at?: string
		work_duration?: string
		breaks_duration?: string
		total_duration?: number
		overtime?: number
		about?: string
	}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const confirm = useConfirm()

const isManualClockOutOpen = ref(false)

const manualClockOutActions = computed(() => {
	if (!props.manual_clock_out?.can_edit || !props.manual_clock_out?.has_open_tracker || !props.manual_clock_out?.route) {
		return []
	}

	return [
		{
			label: trans("Manual Clock Out"),
			icon: "fal fa-plus",
			type: "icon",
			onClick: (event: MouseEvent) => {
				if (props.manual_clock_out?.is_today) {
					const target = event.currentTarget as HTMLElement | null
					if (!target) return

					confirm.require({
						target,
						message: trans("Are you sure you want to manually clock out this user?"),
						icon: "pi pi-exclamation-triangle",
						rejectProps: {
							label: trans("Cancel"),
							severity: "secondary",
							outlined: true,
						},
						acceptProps: {
							label: trans("Yes"),
							severity: "danger",
						},
						accept: () => {
							const manualClockOutRoute = props.manual_clock_out?.route
							if (!manualClockOutRoute) return

							router.post(
								route(manualClockOutRoute.name, manualClockOutRoute.parameters),
								manualClockOutRoute.body ?? {},
								{
									preserveScroll: true,
									preserveState: false,
								}
							)
						},
					})
				} else {
					isManualClockOutOpen.value = true
				}
			},
		},
	]
})

const component = computed(() => {
	const components: Component = {
		time_trackers: TableTimeTrackers,
		clockings: TableClockings,
		history: TableHistories,
	}

	return components[currentTab.value]
})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="grid grid-cols-2 divide-x divide-gray-200 px-3 py-5">
		<div></div>

		<div class="px-5 py-1">
			<div class="px-4 sm:px-0">
				<h3 class="text-lg font-semibold">Review Time</h3>
				<p class="mt-1 max-w-2xl text-sm text-gray-500">
					The detail of employee's worktime in a day
				</p>
			</div>

			<div class="mt-4 border-t border-gray-100">
				<dl class="divide-y divide-gray-100">
					<div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
						<dt class="text-sm text-gray-500">Start</dt>
						<dd class="mt-1 text-sm font-medium sm:col-span-2 sm:mt-0">
							{{ useHMAP(timesheet.work_start_at) }}
						</dd>
					</div>
					<div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
						<dt class="text-sm text-gray-500">End</dt>
						<dd class="mt-1 text-sm font-medium sm:col-span-2 sm:mt-0">
							{{ useHMAP(timesheet.work_end_at) || "-" }}
						</dd>
					</div>
					<div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
						<dt class="text-sm text-gray-500">Breaks</dt>
						<dd class="mt-1 text-sm font-medium sm:col-span-2 sm:mt-0">
							{{ timesheet.breaks_duration || "-" }}
						</dd>
					</div>
					<div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
						<dt class="text-sm text-gray-500">Total worktime</dt>
						<dd class="mt-1 text-sm font-medium sm:col-span-2 sm:mt-0">
							{{ useSecondsToMS(timesheet.total_duration) }}
						</dd>
					</div>
					<div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
						<dt class="text-sm text-gray-500">Overtime</dt>
						<dd class="mt-1 text-sm font-medium sm:col-span-2 sm:mt-0">
							{{ timesheet.overtime ? useSecondsToMS(timesheet.overtime) : "-" }}
						</dd>
					</div>

					<div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
						<dt class="text-sm text-gray-500">About</dt>
						<dd class="mt-1 text-sm font-medium sm:col-span-2 sm:mt-0">
							<span v-if="timesheet.about">{{ timesheet.about }}</span>
							<span v-else class="text-gray-400 italic font-light">{{
								trans("No note.")
							}}</span>
						</dd>
					</div>
				</dl>
			</div>
		</div>
	</div>

	<Tabs
		:current="currentTab"
		:navigation="tabs['navigation']"
		@update:tab="handleTabUpdate">
		<template #right>
			<ConfirmPopup />
			<TabsRightActions :actions="manualClockOutActions" />
		</template>
	</Tabs>
	<component
		:is="component"
		:data="props[currentTab as keyof typeof props]"
		:tab="currentTab"></component>

	<ManualClockOutModal
		v-if="props.manual_clock_out?.route"
		:isOpen="isManualClockOutOpen"
		:submitRoute="props.manual_clock_out.route"
		:timesheetDate="pageHead.title"
		@onClose="isManualClockOutOpen = false"
	/>
</template>
