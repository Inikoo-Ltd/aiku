<script setup lang="ts">
import { ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Paginator from "primevue/paginator"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBarcode, faPrint, faSearch } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OrgStockLabelModal from "@/Components/Warehouse/Inventory/OrgStockLabelModal.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faBarcode, faPrint, faSearch)

interface Barcode {
	level: string
	number: string | null
}

interface AgentBarcodeRow {
	id: number
	code: string
	name: string
	organisation_code: string
	barcodes: Barcode[]
	label_options_route: routeType
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	search: string
	data: {
		data: AgentBarcodeRow[]
		current_page: number
		per_page: number
		total: number
	}
}>()

const levelLabels: Record<string, string> = {
	unit: ctrans("Unit"),
	sko: ctrans("SKO"),
	carton: ctrans("Carton"),
}

const search = ref(props.search ?? "")

const visit = (parameters: Record<string, string | number>) =>
	router.get(route(route().current() as string, route().params), parameters, { preserveState: true, preserveScroll: true, replace: true })

watch(search, debounce((value: string) => visit(value ? { search: value } : {}), 400))

const onPage = (event: { page: number }) => visit({ ...(search.value ? { search: search.value } : {}), page: event.page + 1 })

const isLabelModalOpen = ref(false)
const loadingKey = ref<string | null>(null)
const labelLevel = ref("unit")
const labelOptions = ref<any>(null)
const labelRoute = ref<routeType | null>(null)

const openLabelModal = async (row: AgentBarcodeRow, level: string) => {
	if (loadingKey.value) {
		return
	}

	loadingKey.value = `${row.id}-${level}`

	try {
		const { data } = await axios.get(route(row.label_options_route.name, row.label_options_route.parameters))
		labelOptions.value = data.options
		labelRoute.value = data.label_route
		labelLevel.value = level
		isLabelModalOpen.value = true
	} catch {
		notify({ title: ctrans("Something went wrong"), text: ctrans("Could not load the label options"), type: "error" })
	} finally {
		loadingKey.value = null
	}
}
</script>

<template>
	<div>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="mx-4 mt-4 space-y-3">
		<IconField class="w-full sm:w-96">
			<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
			<InputText v-model="search" :placeholder="ctrans('Search by code, name or barcode')" class="w-full" />
		</IconField>

		<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<p v-if="!data.data.length" class="px-4 py-10 text-center text-sm text-gray-400">
				{{ ctrans("No products found") }}
			</p>
			<ul v-else class="divide-y divide-gray-100">
				<li v-for="row in data.data" :key="row.id" class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center">
					<div class="min-w-0 lg:w-80 lg:shrink-0">
						<div class="flex items-center gap-2">
							<span class="font-semibold text-gray-900">{{ row.code }}</span>
							<span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ row.organisation_code }}</span>
						</div>
						<p class="truncate text-sm text-gray-500">{{ row.name }}</p>
					</div>
					<div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-3">
						<div v-for="barcode in row.barcodes" :key="barcode.level"
							class="flex items-center justify-between gap-2 rounded-md border border-gray-100 px-3 py-2">
							<div class="min-w-0">
								<div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ levelLabels[barcode.level] ?? barcode.level }}</div>
								<div class="truncate font-mono text-sm" :class="barcode.number ? 'text-gray-800' : 'italic text-gray-300'">
									{{ barcode.number ?? ctrans("No barcode") }}
								</div>
							</div>
							<button v-if="barcode.number" type="button"
								v-tooltip="ctrans('Print PDF label')"
								class="shrink-0 rounded p-1.5 text-gray-400 transition hover:bg-[--app-accent-soft] hover:text-[--app-accent] disabled:cursor-wait"
								:disabled="loadingKey !== null"
								@click="openLabelModal(row, barcode.level)">
								<FontAwesomeIcon :icon="loadingKey === `${row.id}-${barcode.level}` ? 'fal fa-barcode' : 'fal fa-print'"
									:class="{ 'animate-pulse': loadingKey === `${row.id}-${barcode.level}` }" fixed-width aria-hidden="true" />
							</button>
						</div>
					</div>
				</li>
			</ul>
		</div>

		<Paginator v-if="data.total > data.per_page"
			:rows="data.per_page"
			:totalRecords="data.total"
			:first="(data.current_page - 1) * data.per_page"
			@page="onPage" />
	</div>

	<OrgStockLabelModal
		v-if="labelOptions && labelRoute"
		:isOpen="isLabelModalOpen"
		:level="labelLevel"
		:labelRoute="labelRoute"
		:options="labelOptions"
		@onClose="isLabelModalOpen = false" />
	</div>
</template>
