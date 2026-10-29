<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-29 09:44
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPrint, faTimes } from "@fal"
import { faSpinnerThird } from "@fad"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faPrint, faTimes, faSpinnerThird)

type Level = "unit" | "sko" | "carton"

interface LabelsPlan {
	organisations: { id: number; code: string; name: string; products: number }[]
	organisation_id: number | null
	products: { id: number; code: string; name: string | null; org_stock_id: number | null; sko: string | null; skipped: Record<Level, string | null> }[]
	sizes: Record<Level, { key: string; label: string }[]>
	default_size: string
	max_copies: number
	max_labels: number
}

const props = defineProps<{
	supplierProductIds: number[]
	planRoute: routeType
	pdfRoute: routeType
}>()

const visible = defineModel<boolean>("visible", { default: false })

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
const fullWidthPt = { pcInputText: { root: { class: "!w-full" } } }

const plan = ref<LabelsPlan | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const level = ref<Level>("unit")
const organisationId = ref<number | null>(null)
const sizes = ref<Partial<Record<Level, string>>>({})
const copies = ref(1)

const fetchPlan = async (organisation: number | null = null) => {
	isLoading.value = true
	errorMessage.value = null
	try {
		const { data } = await axios.get<LabelsPlan>(route(props.planRoute.name, {
			...props.planRoute.parameters,
			supplier_products: props.supplierProductIds.join(","),
			organisation: organisation ?? undefined,
		}))
		plan.value = data
		organisationId.value = data.organisation_id
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.message || ctrans("The labels could not be prepared, try again")
	} finally {
		isLoading.value = false
	}
}

watch(visible, (isVisible) => {
	if (isVisible) {
		fetchPlan(organisationId.value)
	}
})

const onChangeOrganisation = (organisation: number) => fetchPlan(organisation)

const levelOptions = computed(() => ([
	{ label: ctrans("Unit"), value: "unit" as Level, count: countPrintable("unit") },
	{ label: ctrans("SKO"), value: "sko" as Level, count: countPrintable("sko") },
	{ label: ctrans("Carton"), value: "carton" as Level, count: countPrintable("carton") },
]))

function countPrintable(forLevel: Level): number | undefined {
	return plan.value ? plan.value.products.filter((product) => !product.skipped[forLevel]).length : undefined
}

const sizeOptions = computed(() => plan.value?.sizes[level.value] ?? [])

const selectedSize = computed({
	get: () => {
		const keys = sizeOptions.value.map((size) => size.key)
		const chosen = sizes.value[level.value]
		if (chosen && keys.includes(chosen)) {
			return chosen
		}
		return keys.includes(plan.value?.default_size ?? "") ? plan.value?.default_size : keys[0]
	},
	set: (size) => {
		sizes.value[level.value] = size
	},
})

const printableProducts = computed(() => plan.value?.products.filter((product) => !product.skipped[level.value]) ?? [])
const skippedProducts = computed(() => plan.value?.products.filter((product) => product.skipped[level.value]) ?? [])
const totalLabels = computed(() => printableProducts.value.length * (copies.value || 1))
const isOverLimit = computed(() => Boolean(plan.value && totalLabels.value > plan.value.max_labels))

const printLabels = () => {
	window.open(route(props.pdfRoute.name, {
		...props.pdfRoute.parameters,
		supplier_products: printableProducts.value.map((product) => product.id).join(","),
		level: level.value,
		organisation: organisationId.value ?? undefined,
		size: selectedSize.value,
		copies: copies.value || 1,
	}), "_blank")
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Print barcode labels')" class="w-full max-w-2xl">
		<div v-if="isLoading && !plan" class="flex items-center gap-2 py-6 text-sm text-gray-500">
			<FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
			{{ ctrans("Preparing labels…") }}
		</div>

		<div v-else-if="plan" class="space-y-4 text-sm" :class="{ 'opacity-60 pointer-events-none': isLoading }">
			<div>
				<div class="mb-1 text-xs font-semibold text-gray-600">{{ ctrans("Level") }}</div>
				<SegmentedToggle v-model="level" :options="levelOptions" :ariaLabel="ctrans('Level')" />
			</div>

			<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
				<label class="block">
					<span class="text-xs font-semibold text-gray-600">{{ ctrans("Organisation") }}</span>
					<Select
						:modelValue="organisationId"
						:options="plan.organisations"
						optionValue="id"
						:optionLabel="(organisation) => `${organisation.code} (${organisation.products})`"
						:placeholder="ctrans('No SKO found')"
						class="mt-1 w-full"
						:class="fieldFocusClass"
						@update:modelValue="onChangeOrganisation"
					/>
				</label>
				<label class="block">
					<span class="text-xs font-semibold text-gray-600">{{ ctrans("Label size (mm)") }}</span>
					<Select v-model="selectedSize" :options="sizeOptions" optionLabel="label" optionValue="key" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<label class="block">
					<span class="text-xs font-semibold text-gray-600">{{ ctrans("Copies per product") }}</span>
					<InputNumber v-model="copies" :min="1" :max="plan.max_copies" showButtons class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthPt" />
				</label>
			</div>

			<div class="text-gray-700">
				{{ ctrans(":labels labels for :products of :selected products, one label per page", { labels: totalLabels, products: printableProducts.length, selected: plan.products.length }) }}
			</div>

			<div v-if="skippedProducts.length" class="max-h-56 overflow-y-auto rounded border border-amber-200">
				<table class="w-full text-xs">
					<thead class="whitespace-nowrap bg-amber-50 text-left text-amber-800">
						<tr>
							<th class="px-2 py-1 font-semibold">{{ ctrans("Not printed") }}</th>
							<th class="px-2 py-1 font-semibold">{{ ctrans("Reason") }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="product in skippedProducts" :key="product.id" class="border-t border-amber-100">
							<td class="whitespace-nowrap px-2 py-1 font-medium text-gray-800">{{ product.code }}</td>
							<td class="px-2 py-1 text-gray-600">{{ product.skipped[level] }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<p v-if="isOverLimit" class="text-xs text-red-600">
				{{ ctrans("One file holds at most :max labels, select fewer products or copies", { max: plan.max_labels }) }}
			</p>
		</div>

		<p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

		<template #footer>
			<Button type="tertiary" :label="ctrans('Close')" @click="visible = false">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button
				type="tertiary"
				:label="ctrans('Print :count labels', { count: totalLabels })"
				:disabled="!printableProducts.length || isOverLimit || isLoading"
				@click="printLabels"
			>
				<template #icon>
					<FontAwesomeIcon icon="fal fa-print" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</Dialog>
</template>
