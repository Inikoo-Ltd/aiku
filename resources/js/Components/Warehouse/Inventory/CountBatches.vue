<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Oct 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClipboardCheck, faPlus, faTrashAlt } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"

library.add(faClipboardCheck, faPlus, faTrashAlt)

interface Batch {
	batch_code_id: number
	code: string
	expiry_date: string | null
	quantity: number
}

interface CountLocation {
	location_org_stock_id: number
	code: string
	quantity: number
	batches: Batch[]
}

const props = defineProps<{
	batchCount: { locations: CountLocation[] }
}>()

const locale = useLocaleStore()
const isOpen = ref(false)
const isSaving = ref(false)
const errorMessage = ref<string | null>(null)
const locationOrgStockId = ref<number | null>(null)
const counted = ref<{ batch_code_id: number | null; code: string; expiry: Date | null; quantity: number | null }[]>([])
const withoutBatch = ref<number | null>(0)

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const location = computed(() => props.batchCount.locations.find((candidate) => candidate.location_org_stock_id === locationOrgStockId.value) ?? null)
const total = computed(() => counted.value.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0) + (Number(withoutBatch.value) || 0))
const difference = computed(() => Math.round((total.value - (location.value?.quantity ?? 0)) * 10000) / 10000)

watch(location, (current) => {
	errorMessage.value = null
	if (!current) {
		counted.value = []
		withoutBatch.value = 0
		return
	}
	counted.value = current.batches.map((batch) => ({ batch_code_id: batch.batch_code_id, code: batch.code, expiry: batch.expiry_date ? fromIsoDate(batch.expiry_date) : null, quantity: batch.quantity }))
	const batched = current.batches.reduce((sum, batch) => sum + batch.quantity, 0)
	withoutBatch.value = Math.max(0, Math.round((current.quantity - batched) * 10000) / 10000)
})

function open() {
	locationOrgStockId.value = props.batchCount.locations.length === 1 ? props.batchCount.locations[0].location_org_stock_id : null
	isOpen.value = true
}

function addBatch() {
	counted.value.push({ batch_code_id: null, code: "", expiry: null, quantity: null })
}

async function save() {
	if (!location.value) {
		return
	}
	isSaving.value = true
	errorMessage.value = null
	try {
		await axios.patch(route("grp.models.location_org_stock.audit", { locationOrgStock: location.value.location_org_stock_id }), {
			quantity: total.value,
			batches: counted.value
				.filter((row) => row.batch_code_id || row.code.trim() !== "")
				.map((row) => (row.batch_code_id
					? { batch_code_id: row.batch_code_id, quantity: Number(row.quantity) || 0 }
					: { code: row.code.trim(), expiry_date: row.expiry ? toLocalIsoDate(row.expiry) : null, quantity: Number(row.quantity) || 0 })),
		})
		isOpen.value = false
		notify({ title: ctrans("Success"), text: ctrans("Batches counted"), type: "success" })
		router.reload()
	} catch (error: any) {
		const errors = error?.response?.data?.errors
		errorMessage.value = errors ? (Object.values(errors).flat()[0] as string) : error?.response?.data?.message || ctrans("Failed to save the count")
	} finally {
		isSaving.value = false
	}
}
</script>

<template>
	<div>
		<Button
			v-if="batchCount.locations.length"
			:label="ctrans('Count batches')"
			icon="fal fa-clipboard-check"
			type="secondary"
			@click="open" />

		<Dialog v-model:visible="isOpen" modal :header="ctrans('Count batches in a location')" class="w-full max-w-xl" :closeOnEscape="false">
			<p class="text-sm text-gray-500 mb-3">
				{{ ctrans("Count what is on the shelf batch by batch, as printed on the goods. What you count replaces what the stock says is there; anything you cannot read goes under no batch.") }}
			</p>

			<Select
				v-model="locationOrgStockId"
				:options="batchCount.locations"
				optionLabel="code"
				optionValue="location_org_stock_id"
				:placeholder="ctrans('Location')"
				class="w-full mb-3"
				:class="fieldFocusClass" />

			<template v-if="location">
				<div class="space-y-2">
					<div class="grid grid-cols-[1fr_9rem_6rem_2rem] gap-2 text-xs text-gray-500">
						<span>{{ ctrans("Batch code") }}</span>
						<span>{{ ctrans("Best-before") }}</span>
						<span class="text-right">{{ ctrans("SKOs") }}</span>
						<span></span>
					</div>
					<div v-for="(row, index) in counted" :key="index" class="grid grid-cols-[1fr_9rem_6rem_2rem] gap-2 items-center">
						<InputText v-model="row.code" size="small" :disabled="!!row.batch_code_id" :class="fieldFocusClass" :aria-label="ctrans('Batch code')" />
						<DatePicker v-model="row.expiry" dateFormat="dd/mm/yy" placeholder="dd/mm/yyyy" :disabled="!!row.batch_code_id" showIcon iconDisplay="input" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full !py-1 !text-xs' } } }" :aria-label="ctrans('Best-before')" />
						<InputNumber v-model="row.quantity" :min="0" :maxFractionDigits="4" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full !text-right' } } }" :aria-label="ctrans('SKOs')" />
						<button v-if="!row.batch_code_id" type="button" class="text-gray-400 hover:text-red-500" :aria-label="ctrans('Remove')" @click="counted.splice(index, 1)">
							<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
						</button>
						<span v-else></span>
					</div>
					<div class="grid grid-cols-[1fr_9rem_6rem_2rem] gap-2 items-center">
						<span class="text-sm text-gray-600 col-span-2">{{ ctrans("No batch") }}</span>
						<InputNumber v-model="withoutBatch" :min="0" :maxFractionDigits="4" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full !text-right' } } }" :aria-label="ctrans('No batch')" />
						<span></span>
					</div>
				</div>

				<div class="flex items-center justify-between mt-3 text-sm">
					<Button :label="ctrans('Add batch')" icon="fal fa-plus" type="tertiary" size="xs" @click="addBatch" />
					<span :class="difference !== 0 ? 'text-amber-600' : 'text-gray-500'">
						{{ ctrans(":total counted, the stock says :quantity", { total: locale.number(total), quantity: locale.number(location.quantity) }) }}
					</span>
				</div>
			</template>

			<p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>

			<template #footer>
				<Button :label="ctrans('Cancel')" type="tertiary" @click="isOpen = false" />
				<Button :label="ctrans('Save')" type="save" :loading="isSaving" :disabled="!location" @click="save" />
			</template>
		</Dialog>
	</div>
</template>
