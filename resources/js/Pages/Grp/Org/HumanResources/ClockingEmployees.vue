<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 17 Sept 2022 00:32:56 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, onMounted, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { useEchoEmployeeClocking } from "@/Stores/echo-employee-clocking"
import EmployeeClockingPanel from "./EmployeeClockingPanel.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faEnvelope,
	faIdCard,
	faPhone,
	faSignature,
	faUser,
	faBuilding,
	faBirthdayCake,
	faVenusMars,
	faHashtag,
	faHeading,
	faHospitalUser,
	faClock,
	faPaperclip,
	faTimes,
	faCameraRetro,
	faQrcode,
	faPlus,
} from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import TableTimesheetsEmployee from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheetsEmployee.vue"
import TableLeaves from "@/Components/Tables/Grp/Org/HumanResources/TableLeaves.vue"
import TableAttendanceAdjustments from "@/Components/Tables/Grp/Org/HumanResources/TableAttendanceAdjustments.vue"
import TableOvertimeEmployee from "@/Components/Tables/Grp/Org/HumanResources/TableOvertimeEmployee.vue"
import EmployeeCalendar from "@/Components/HumanResources/EmployeeCalendar.vue"

library.add(
	faEnvelope,
	faIdCard,
	faPhone,
	faSignature,
	faUser,
	faBuilding,
	faBirthdayCake,
	faVenusMars,
	faHashtag,
	faHeading,
	faHospitalUser,
	faClock,
	faPaperclip,
	faTimes,
	faCameraRetro,
	faQrcode,
	faPlus
)

type EmployeeCalendarData = {
	year?: number
	month?: number | null
	holidays?: {
		date: string
		label: string
	}[]
	holidayRanges?: {
		from: string
		to: string
		label: string
	}[]
}

const props = defineProps<{
	data: object
	title: string
	employeeId?: number | null
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: Record<string, any>
	}
	timesheets?: Record<string, any>
	clock_in_out?: Record<string, any>
	leaves?: Record<string, any>
	adjustments?: Record<string, any>
	overtime?: Record<string, any>
	calendar?: EmployeeCalendarData
}>()

let currentTab = ref(props.tabs.current)

// Any clock in/out from any device (kiosk PIN/barcode, or the employee's own QR scan)
// broadcasts here, so this page's status refreshes live without a manual reload.
onMounted(() => {
	if (props.employeeId) {
		useEchoEmployeeClocking().subscribe(props.employeeId)
	}
})
const isRequestLeaveModalOpen = ref(false)
const isRequestOvertimeModalOpen = ref(false)

const openRequestLeaveModal = () => {
	isRequestLeaveModalOpen.value = true
}

const handleTabUpdate = (tabSlug: string) => {
	const url = new URL(window.location.href)
	url.searchParams.forEach((_, key) => {
		if (
			key.startsWith("timesheet_") ||
			key === "year" ||
			key === "month" ||
			key.startsWith("requested_")
		) {
			url.searchParams.delete(key)
		}
	})

	url.searchParams.set("tab", tabSlug)
	window.history.replaceState({}, "", url)

	useTabChange(tabSlug, currentTab)
}

const component = computed(() => {
	const components = {
		timesheets: TableTimesheetsEmployee,
		clock_in_out: EmployeeClockingPanel,
		leaves: TableLeaves,
		adjustments: TableAttendanceAdjustments,
		overtime: TableOvertimeEmployee,
	}
	return components[currentTab.value as keyof typeof components]
})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button
				v-if="currentTab === 'leaves'"
				:label="trans('Request Leave')"
				icon="fal fa-plus"
				type="create"
				@click="openRequestLeaveModal" />
		</template>
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

	<EmployeeCalendar v-if="currentTab === 'calendar'" :calendar="calendar" />

	<component
		v-else
		:is="component"
		:data="
			currentTab === 'leaves'
				? leaves?.data
				: currentTab === 'adjustments'
					? adjustments?.data
					: currentTab === 'overtime'
						? overtime?.data
						: timesheets?.data
		"
		:statistics="timesheets?.statistics"
		:balance="leaves?.balance"
		:typeOptions="leaves?.type_options"
		:annualSubmittedDays="leaves?.annual_submitted_days"
		:annualRemainingAfterSubmission="leaves?.annual_remaining_after_submission"
		:medicalRequestCount="leaves?.medical_request_count"
		:unpaidRequestCount="leaves?.unpaid_request_count"
		:holidays="currentTab === 'leaves' ? (leaves?.holidays ?? []) : undefined"
		:organisation="
			currentTab === 'leaves'
				? leaves?.organisation
				: currentTab === 'adjustments'
					? adjustments?.organisation
					: currentTab === 'overtime'
						? overtime?.organisation
						: null
		"
		:overtimeTypeOptions="currentTab === 'overtime' ? overtime?.overtimeTypeOptions : undefined"
		:tab="currentTab"
		:isRequestLeaveModalOpen="isRequestLeaveModalOpen"
		:isRequestOvertimeModalOpen="isRequestOvertimeModalOpen"
		:activeTimeTracker="clock_in_out?.active_time_tracker"
		:clockingStatus="clock_in_out?.clocking_status"
		:todayTimesheet="clock_in_out?.today_timesheet"
		:lastClockIn="clock_in_out?.last_clock_in"
		:lastClockOut="clock_in_out?.last_clock_out"
		:clockingSessions="clock_in_out?.clocking_sessions"
		:timezone="clock_in_out?.timezone"
		:availableMethods="clock_in_out?.available_methods"
		:pin="clock_in_out?.pin"
		@update:isRequestLeaveModalOpen="isRequestLeaveModalOpen = $event"
		@update:isRequestOvertimeModalOpen="isRequestOvertimeModalOpen = $event">
	</component>
</template>
