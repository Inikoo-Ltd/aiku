<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Paginator from "primevue/paginator"
import Checkbox from "primevue/checkbox"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle, faPrint, faSearch, faTimes } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OrgStockLabelModal from "@/Components/Warehouse/Inventory/OrgStockLabelModal.vue"
import AgentBulkBarcodeLabelsDialog from "@/Components/Procurement/AgentBulkBarcodeLabelsDialog.vue"
import type { BulkLabels } from "@/Components/Procurement/AgentBulkBarcodeLabelsDialog.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { barcodePackContents } from "@/Composables/barcodePackContents"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faExclamationTriangle, faPrint, faSearch, faTimes)

interface Barcode {
	level: string
	number: string | null
	quantity?: number | null
	packs?: number | null
	warning?: string | null
}

interface AgentBarcodeRow {
	id: number
	code: string
	name: string
	organisation_code: string
	image_thumbnail: object | null
	image_full: { original_url: string; download_url: string } | null
	barcodes: Barcode[]
	label_options_route: routeType
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	search: string
	organisation_filter: {
		selected: string | null
		options: { label: string; value: string; count: number; tooltip: string }[]
	}
	bulk_labels: BulkLabels
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
const organisation = ref<string | null>(props.organisation_filter.selected)

const filters = () => ({
	...(search.value ? { search: search.value } : {}),
	...(organisation.value ? { organisation: organisation.value } : {}),
})

const visit = (parameters: Record<string, string | number>) =>
	router.get(route(route().current() as string, route().params), parameters, { preserveState: true, preserveScroll: true, replace: true })

watch(search, debounce(() => visit(filters()), 400))

watch(organisation, () => visit(filters()))

const onPage = (event: { page: number }) => visit({ ...filters(), page: event.page + 1 })

const selected = ref<Record<number, boolean>>({})

const selectedIds = computed(() => Object.keys(selected.value).filter((id) => selected.value[Number(id)]).map(Number))

const pageIds = computed(() => props.data.data.map((row) => row.id))

const pageSelectedCount = computed(() => pageIds.value.filter((id) => selected.value[id]).length)

const isPageSelected = computed({
	get: () => pageIds.value.length > 0 && pageSelectedCount.value === pageIds.value.length,
	set: (value: boolean) => pageIds.value.forEach((id) => (selected.value[id] = value)),
})

const clearSelection = () => {
	selected.value = {}
}

const isBulkDialogOpen = ref(false)

const checkboxPt = {
	box: ({ context }: { context: { checked: boolean; indeterminate?: boolean } }) => ({
		class: context.checked || context.indeterminate ? "!border-[var(--app-accent)] !bg-[var(--app-accent)]" : "",
	}),
	icon: { class: "!text-[var(--app-accent-text)]" },
}

const isLabelModalOpen = ref(false)
const loadingKey = ref<string | null>(null)
const labelLevel = ref("unit")
const labelOptions = ref<any>(null)
const labelRoute = ref<routeType | null>(null)
const labelError = ref<{ key: string; message: string } | null>(null)

const openLabelModal = async (row: AgentBarcodeRow, level: string) => {
	if (loadingKey.value) {
		return
	}

	loadingKey.value = `${row.id}-${level}`
	labelError.value = null

	try {
		const { data } = await axios.get(route(row.label_options_route.name, row.label_options_route.parameters))
		labelOptions.value = data.options
		labelRoute.value = data.label_route
		labelLevel.value = level
		isLabelModalOpen.value = true
	} catch {
		labelError.value = { key: `${row.id}-${level}`, message: ctrans("Could not load the label options") }
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
		<div class="flex flex-wrap items-center gap-3">
			<IconField class="w-full sm:w-96">
				<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
				<InputText v-model="search" :placeholder="ctrans('Search by code, name or barcode')" class="w-full" />
			</IconField>
			<PillFilterBar v-if="organisation_filter.options.length > 1" :label="ctrans('AW company')" :options="organisation_filter.options"
				:selected="organisation" @select="(value) => (organisation = value)" />
		</div>

		<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<div v-if="data.data.length" class="flex flex-wrap items-center gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm">
				<label class="flex cursor-pointer items-center gap-2 text-gray-600">
					<Checkbox v-model="isPageSelected" :binary="true"
						:indeterminate="pageSelectedCount > 0 && pageSelectedCount < pageIds.length" :pt="checkboxPt" />
					{{ ctrans("Select all on this page") }}
				</label>
				<template v-if="selectedIds.length">
					<span class="text-gray-400">·</span>
					<span class="tabular-nums text-gray-700">{{ ctrans(":count selected", { count: selectedIds.length }) }}</span>
					<button type="button" class="text-xs text-gray-400 hover:text-gray-700" @click="clearSelection">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
						{{ ctrans("Clear") }}
					</button>
				</template>
				<Button class="ml-auto" type="tertiary" size="s" :label="ctrans('Print labels')" :disabled="!selectedIds.length"
					@click="isBulkDialogOpen = true">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-print" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>

			<p v-if="!data.data.length" class="px-4 py-10 text-center text-sm text-gray-400">
				{{ ctrans("No products found") }}
			</p>
			<ul v-else class="divide-y divide-gray-100">
				<li v-for="row in data.data" :key="row.id" class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center"
					:class="{ 'bg-[var(--app-accent-soft)]': selected[row.id] }">
					<div class="flex min-w-0 items-center gap-3 lg:w-96 lg:shrink-0">
						<Checkbox v-model="selected[row.id]" :binary="true" :pt="checkboxPt" :aria-label="row.code" />
						<ZoomableImage :src="row.image_thumbnail" :full="row.image_full" :alt="row.code" class="h-12 w-12 shrink-0" />
						<div class="min-w-0">
							<div class="flex items-center gap-2">
								<span class="whitespace-nowrap font-semibold text-gray-900">{{ row.code }}</span>
								<span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ row.organisation_code }}</span>
							</div>
							<p class="truncate text-sm text-gray-500">{{ row.name }}</p>
						</div>
					</div>
					<div class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-3">
						<div v-for="barcode in row.barcodes" :key="barcode.level"
							class="rounded-md border border-gray-100 bg-white px-3 py-2">
							<div class="flex items-center justify-between gap-2">
								<div class="min-w-0">
									<div class="flex flex-wrap items-baseline gap-x-2">
										<span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ levelLabels[barcode.level] ?? barcode.level }}</span>
										<span v-if="barcodePackContents(barcode)" class="whitespace-nowrap text-xs text-gray-400">{{ barcodePackContents(barcode) }}</span>
									</div>
									<div class="flex min-w-0 items-center gap-1.5">
										<span class="truncate font-mono text-sm" :class="barcode.number ? 'text-gray-800' : 'italic text-gray-300'">
											{{ barcode.number ?? ctrans("No barcode") }}
										</span>
										<FontAwesomeIcon v-if="barcode.number && barcode.warning" v-tooltip="barcode.warning"
											icon="fal fa-exclamation-triangle" class="shrink-0 text-amber-500" fixed-width aria-hidden="true" />
									</div>
								</div>
								<button v-if="barcode.number" type="button"
									v-tooltip="ctrans('Print PDF label')"
									:aria-label="ctrans('Print PDF label')"
									class="shrink-0 rounded p-1.5 text-gray-400 transition hover:bg-[var(--app-accent-soft)] hover:text-[var(--app-accent)] disabled:cursor-wait"
									:disabled="loadingKey !== null"
									@click="openLabelModal(row, barcode.level)">
									<LoadingIcon v-if="loadingKey === `${row.id}-${barcode.level}`" />
									<FontAwesomeIcon v-else icon="fal fa-print" fixed-width aria-hidden="true" />
								</button>
							</div>
							<p v-if="labelError?.key === `${row.id}-${barcode.level}`" class="mt-1 text-xs text-red-600">{{ labelError.message }}</p>
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

	<AgentBulkBarcodeLabelsDialog v-model:visible="isBulkDialogOpen" :orgStockIds="selectedIds" :bulkLabels="bulk_labels" />
	</div>
</template>
