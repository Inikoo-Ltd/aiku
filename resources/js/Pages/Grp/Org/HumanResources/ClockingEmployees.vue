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
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ScanQrUser from "./ScanQrUser.vue"
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

const props = defineProps<{
	data: object
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: Record<string, any>
	}
	timesheets?: Record<string, any>
	scan_qr_code?: Record<string, any>
	leaves?: Record<string, any>
	adjustments?: Record<string, any>
	overtime?: Record<string, any>
}>()

let currentTab = ref(props.tabs.current)
const isRequestLeaveModalOpen = ref(false)
const isRequestOvertimeModalOpen = ref(false)

const openRequestLeaveModal = () => {
	isRequestLeaveModalOpen.value = true
}

const handleTabUpdate = (tabSlug: string) => {
	const url = new URL(window.location.href)
	url.searchParams.forEach((_, key) => {
		if (key.startsWith("timesheets_")) {
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
		scan_qr_code: ScanQrUser,
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
	<component
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
		@update:isRequestLeaveModalOpen="isRequestLeaveModalOpen = $event"
		@update:isRequestOvertimeModalOpen="isRequestOvertimeModalOpen = $event">
	</component>
</template>
