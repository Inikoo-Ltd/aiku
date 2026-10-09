<!--
  - Author: INI-1009 - Packaging Report Generator
  - Created: 2025-02-09
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxes, faDownload, faTasks, faFileCsv } from "@fal"
import DatePicker from "primevue/datepicker"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import EprPackagingCompleteness from "@/Components/Reports/EprPackagingCompleteness.vue"
import UkPackagingReturn from "@/Components/Reports/UkPackagingReturn.vue"
import EprShipmentPackaging from "@/Components/Reports/EprShipmentPackaging.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faBoxes, faDownload, faTasks, faFileCsv)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: { current: string; navigation: {} }
	period: { from: string; to: string }
	downloadRoute: { name: string; parameters: Record<string, string> }
	completeness?: any
	uk_return?: any
	shipment?: any
	organisationId: number
	materials: Record<string, string>
	ownBrandImports: boolean
	ukReturnRoute: { name: string; parameters: Record<string, string> }
}>()

const currentTab = ref(props.tabs.current)

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const tabsWithData = ["completeness", "uk_return", "shipment"]

const visit = (period: { from: string; to: string }, ownBrandImports = props.ownBrandImports) => {
	router.get(
		route(route().current() as string, props.downloadRoute.parameters),
		{ ...period, tab: currentTab.value, ...(ownBrandImports ? { own_brand_imports: 1 } : {}) },
		{
			preserveState: true,
			preserveScroll: true,
			only: ["period", "tabs", "ownBrandImports", ...(tabsWithData.includes(currentTab.value) ? [currentTab.value] : [])],
		}
	)
}

const handleTabUpdate = (tabSlug: string) => {
	if (tabSlug === currentTab.value) return
	currentTab.value = tabSlug
	visit(props.period)
}

const halfYears = computed(() => {
	const today = new Date()
	let year = today.getFullYear()
	let half = today.getMonth() < 6 ? 1 : 2
	const options = []
	for (let i = 0; i < 4; i++) {
		options.unshift({
			label: `H${half} ${year}`,
			value: half === 1 ? `${year}-01-01|${year}-06-30` : `${year}-07-01|${year}-12-31`,
		})
		half === 1 ? ((half = 2), year--) : (half = 1)
	}
	return options
})

const selectedHalf = computed({
	get: () => `${props.period.from}|${props.period.to}`,
	set: (value: string) => {
		const [from, to] = value.split("|")
		visit({ from, to })
	},
})

const dateModel = (key: "from" | "to") =>
	computed<Date | null>({
		get: () => fromIsoDate(props.period[key]),
		set: (value) => {
			if (!value) return
			visit({ ...props.period, [key]: toLocalIsoDate(value) })
		},
	})
const rangeFrom = dateModel("from")
const rangeTo = dateModel("to")

const datePickerPt = { pcInputText: { root: { class: "!w-32 !py-1 !text-xs" } } }
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const ukReturnUrl = computed(() =>
	route(props.ukReturnRoute.name, { ...props.ukReturnRoute.parameters, from: props.period.from, to: props.period.to, ...(props.ownBrandImports ? { own_brand_imports: 1 } : {}) })
)

const downloadUrl = computed(() => route(props.downloadRoute.name, { ...props.downloadRoute.parameters, start_date: props.period.from, end_date: props.period.to }))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

	<div class="flex flex-wrap items-center gap-3 px-4 pt-4 text-xs text-gray-600">
		<SegmentedToggle v-model="selectedHalf" :options="halfYears" :ariaLabel="ctrans('Half-year')" />
		<div class="flex items-center gap-2">
			<DatePicker v-model="rangeFrom" :maxDate="rangeTo ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
			<span>–</span>
			<DatePicker v-model="rangeTo" :minDate="rangeFrom ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
		</div>
	</div>

	<EprPackagingCompleteness v-if="currentTab === 'completeness'" :data="completeness" />

	<UkPackagingReturn
		v-else-if="currentTab === 'uk_return'"
		:data="uk_return"
		:ownBrandImports="ownBrandImports"
		:downloadUrl="ukReturnUrl"
		:organisationId="organisationId"
		:materials="materials"
		:period="period"
		@update:ownBrandImports="(value) => visit(period, value)" />

	<EprShipmentPackaging v-else-if="currentTab === 'shipment'" :data="shipment" :organisationId="organisationId" :materials="materials" />

	<div v-else class="px-4 py-5 max-w-2xl text-sm text-gray-700 space-y-3">
		<p>{{ ctrans("The four spreadsheets the UK packaging workbook reads, for the period above: one row per SKO with its quantity.") }}</p>
		<ul class="list-disc list-inside text-xs text-gray-600 space-y-0.5">
			<li>{{ ctrans("Buy from UK: bought from suppliers in the UK") }}</li>
			<li>{{ ctrans("Imports: bought from suppliers outside the UK") }}</li>
			<li>{{ ctrans("Sales UK: dispatched to UK addresses") }}</li>
			<li>{{ ctrans("Exports: dispatched outside the UK") }}</li>
		</ul>
		<a :href="downloadUrl">
			<Button type="tertiary" :icon="['fal', 'download']" :label="ctrans('Download the four spreadsheets (zip)')" />
		</a>
	</div>
</template>
