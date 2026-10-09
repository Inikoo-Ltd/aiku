<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faPlus, faTrashAlt } from "@fal"
import { faExclamationTriangle } from "@fas"
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"

library.add(faBarcodeRead, faPlus, faTrashAlt, faExclamationTriangle)

interface Batch {
	code: string
	expiry_date: string | null
	quantity: number
	placed?: number
}

const props = defineProps<{
	batches: Batch[]
	checkedSkos: number
	isBatchTracked: boolean
	route: routeType | null
}>()

const emit = defineEmits<{ (e: "saved"): void }>()

const locale = useLocaleStore()
const isOpen = ref(false)
const isSaving = ref(false)
const rows = ref<{ code: string; expiry: Date | null; quantity: number | null; placed: number }[]>([])
const errorMessage = ref<string | null>(null)

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const total = computed(() => rows.value.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0))
const remaining = computed(() => Math.round((props.checkedSkos - total.value) * 10000) / 10000)
const batchedSkos = computed(() => props.batches.reduce((sum, batch) => sum + Number(batch.quantity), 0))
const isMissing = computed(() => props.isBatchTracked && props.checkedSkos > 0 && batchedSkos.value + 0.00005 < props.checkedSkos)
const isMissingBestBefore = computed(() => props.isBatchTracked && props.batches.some((batch) => !batch.expiry_date))

function open() {
	rows.value = props.batches.length
		? props.batches.map((batch) => ({ code: batch.code, expiry: batch.expiry_date ? fromIsoDate(batch.expiry_date) : null, quantity: Number(batch.quantity), placed: Number(batch.placed ?? 0) }))
		: [{ code: "", expiry: null, quantity: props.checkedSkos, placed: 0 }]
	errorMessage.value = null
	isOpen.value = true
}

function addRow() {
	rows.value.push({ code: "", expiry: null, quantity: remaining.value > 0 ? remaining.value : null, placed: 0 })
}

async function save() {
	if (!props.route) {
		return
	}
	isSaving.value = true
	errorMessage.value = null
	try {
		await axios.patch(route(props.route.name, props.route.parameters), {
			batches: rows.value
				.filter((row) => row.code.trim() !== "" && Number(row.quantity) > 0)
				.map((row) => ({ code: row.code.trim(), expiry_date: row.expiry ? toLocalIsoDate(row.expiry) : null, quantity: row.quantity })),
		})
		isOpen.value = false
		notify({ title: ctrans("Success"), text: ctrans("Batches saved"), type: "success" })
		emit("saved")
	} catch (error: any) {
		const errors = error?.response?.data?.errors
		errorMessage.value = errors ? (Object.values(errors).flat()[0] as string) : error?.response?.data?.message || ctrans("Failed to save the batches")
	} finally {
		isSaving.value = false
	}
}

function formatDate(iso: string | null) {
	return iso ? fromIsoDate(iso).toLocaleDateString(locale.language.code, { day: "numeric", month: "short", year: "numeric" }) : ctrans("No best-before")
}
</script>

<template>
	<div class="flex flex-col items-end gap-y-1 text-xs">
		<button
			v-for="(batch, index) in batches"
			:key="index"
			type="button"
			class="flex items-center gap-x-1.5 text-gray-600 hover:text-[--app-accent] disabled:cursor-default disabled:hover:text-gray-600"
			:disabled="!route"
			@click="open">
			<FontAwesomeIcon icon="fal fa-barcode-read" fixed-width aria-hidden="true" />
			<span class="font-medium">{{ batch.code }}</span>
			<span :class="batch.expiry_date ? 'text-gray-500' : 'text-amber-600'">{{ formatDate(batch.expiry_date) }}</span>
			<span class="text-gray-500">× {{ locale.number(Number(batch.quantity)) }}</span>
		</button>

		<div v-if="isMissing || isMissingBestBefore" class="flex items-center gap-x-1 text-amber-600">
			<FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
			{{ isMissing ? ctrans("Batch tracked: enter the batch code and best-before") : ctrans("Best-before missing") }}
		</div>

		<Button
			v-if="route && !batches.length"
			:label="ctrans('Batch')"
			icon="fal fa-plus"
			:type="isMissing ? 'warning' : 'tertiary'"
			size="xxs"
			@click="open" />

		<Dialog v-model:visible="isOpen" modal :header="ctrans('Batches in this delivery')" class="w-full max-w-xl" :closeOnEscape="false">
			<p class="text-sm text-gray-500 mb-3">
				{{ ctrans("Code and best-before as printed on the goods. Put away takes the batches in this order.") }}
			</p>

			<div class="space-y-2">
				<div class="grid grid-cols-[1fr_9rem_6rem_2rem] gap-2 text-xs text-gray-500">
					<span>{{ ctrans("Batch code") }}</span>
					<span>{{ ctrans("Best-before") }}</span>
					<span class="text-right">{{ ctrans("SKOs") }}</span>
					<span></span>
				</div>
				<div v-for="(row, index) in rows" :key="index" class="grid grid-cols-[1fr_9rem_6rem_2rem] gap-2 items-center">
					<InputText v-model="row.code" size="small" :class="fieldFocusClass" :disabled="row.placed > 0" :aria-label="ctrans('Batch code')" />
					<DatePicker v-model="row.expiry" dateFormat="dd/mm/yy" placeholder="dd/mm/yyyy" showIcon iconDisplay="input" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full !py-1 !text-xs' } } }" :aria-label="ctrans('Best-before')" />
					<InputNumber v-model="row.quantity" :min="row.placed" :maxFractionDigits="4" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full !text-right' } } }" :aria-label="ctrans('SKOs')" />
					<button
						v-if="row.placed <= 0"
						type="button"
						class="text-gray-400 hover:text-red-500"
						:aria-label="ctrans('Remove')"
						@click="rows.splice(index, 1)">
						<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
					</button>
					<span v-else v-tooltip="ctrans('Already put away')" class="text-xs text-gray-400 text-center">{{ locale.number(row.placed) }}</span>
				</div>
			</div>

			<div class="flex items-center justify-between mt-3 text-sm">
				<Button :label="ctrans('Add batch')" icon="fal fa-plus" type="tertiary" size="xs" @click="addRow" />
				<span :class="remaining < 0 ? 'text-red-600' : remaining > 0 ? 'text-amber-600' : 'text-gray-500'">
					{{ ctrans(":total of :checked SKOs checked", { total: locale.number(total), checked: locale.number(checkedSkos) }) }}
				</span>
			</div>

			<p v-if="errorMessage" class="mt-3 text-sm text-red-600">{{ errorMessage }}</p>

			<template #footer>
				<Button :label="ctrans('Cancel')" type="tertiary" @click="isOpen = false" />
				<Button :label="ctrans('Save')" type="save" :loading="isSaving" :disabled="remaining < 0" @click="save" />
			</template>
		</Dialog>
	</div>
</template>
