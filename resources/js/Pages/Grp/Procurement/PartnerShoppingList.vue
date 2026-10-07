<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import PurchaseOrderItemStockInfo from "@/Components/Procurement/PurchaseOrderItemStockInfo.vue"
import PurchaseOrderSuggestButton from "@/Components/Procurement/PurchaseOrderSuggestButton.vue"
import RenderWhenVisible from "@/Components/Utils/RenderWhenVisible.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import Image from "@common/Components/Image.vue"
import ModalPartnerStockList from "@/Components/Procurement/ModalPartnerStockList.vue"
import ModalAutoFillShoppingList from "@/Components/Procurement/ModalAutoFillShoppingList.vue"
import Modal from "@/Components/Utils/Modal.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import { Upload } from "@/types/Upload"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCut, faUpload, faPaperPlane, faIndustryAlt, faBan, faBells } from "@fal"
library.add(faCut, faUpload, faPaperPlane, faIndustryAlt, faBan, faBells)
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import ConfirmDialog from "primevue/confirmdialog"
import ConfirmPopup from "primevue/confirmpopup"
import ProgressBar from "primevue/progressbar"
import { useConfirm } from "primevue/useconfirm"

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: object
	orgPartner: { id: number; slug: string; currency: string }
	draftsCount: number
	hubSuggestionsCount: number
	partnerCode: string
	isSentView: boolean
	linesValue: number
	orgStockFetchRoute: { name: string; parameters: object }
	filterGroups: { key: string; label: string; options: { value: string; label: string; count: number }[] }[]
	upload_excel: {
		title: { label: string; information: string }
		progressDescription: string
		preview_template: { header: string[]; rows: object[] }
		upload_spreadsheet: Upload
	}
}>()

const isUploadOpen = ref(false)

const isModalOpen = ref(false)
const isAutoFillOpen = ref(false)
const isTableLoading = ref(false)
const tableLoadingEvents = {
	onStart: () => (isTableLoading.value = true),
	onFinish: () => (isTableLoading.value = false),
}
const confirm = useConfirm()

const selectedFilters = (key: string) =>
	(new URLSearchParams(location.search).get(`filter[${key}]`) ?? "").split(",").filter(Boolean)

const toggleFilter = (key: string, value: string) => {
	const url = new URL(location.href)
	const selected = selectedFilters(key).includes(value)
		? selectedFilters(key).filter((item) => item !== value)
		: [...selectedFilters(key), value]
	selected.length
		? url.searchParams.set(`filter[${key}]`, selected.join(","))
		: url.searchParams.delete(`filter[${key}]`)
	url.searchParams.delete("page")
	router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, replace: true, ...tableLoadingEvents })
}
const routeParams = route().params

function confirmDeleteAll() {
	confirm.require({
		group: "partner-shopping-list",
		header: ctrans("Delete all open items"),
		message: ctrans(
			"Remove every draft and sent line not yet taken by the partner? Lines the partner already took are kept."
		),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Delete all"), severity: "danger" },
		accept: () => {
			router.delete(
				route("grp.org.procurement.org_partners.show.shopping_list.destroy_open", [
					routeParams["organisation"],
					props.orgPartner.id,
				]),
				{ preserveScroll: true, ...tableLoadingEvents }
			)
		},
	})
}

function confirmDropHubSuggestions() {
	confirm.require({
		group: "partner-shopping-list",
		header: ctrans("Drop :partner suggestions", { partner: props.partnerCode }),
		message: ctrans(
			"Remove the :count draft lines :partner suggested? Lines you added stay.",
			{ count: String(props.hubSuggestionsCount), partner: props.partnerCode }
		),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Drop them"), severity: "danger" },
		accept: () => {
			router.delete(
				route("grp.org.procurement.org_partners.show.shopping_list.destroy_hub_suggestions", [
					routeParams["organisation"],
					props.orgPartner.id,
				]),
				{ preserveScroll: true, ...tableLoadingEvents }
			)
		},
	})
}

watch(isModalOpen, (isOpen, wasOpen) => {
	if (wasOpen && !isOpen) {
		router.reload({ only: ["data"], ...tableLoadingEvents })
	}
})

const amountOf = (item: { quantity: number; price_per_sko: number | null }) =>
	Number(item.quantity) * Number(item.price_per_sko ?? 0)

const priorities = ["low", "normal", "high", "urgent"]

function updateItem(item: { id: number }, data: Record<string, string | null>) {
	router.patch(
		route("grp.org.procurement.org_partners.show.shopping_list.update", [
			routeParams["organisation"],
			props.orgPartner.id,
			item.id,
		]),
		data,
		{ preserveScroll: true, ...tableLoadingEvents }
	)
}

interface BatchedItem {
	id: number
	org_stock_code: string
	quantity: number
	order_quantum: number
}

const isPartBatch = (item: BatchedItem) =>
	Number(item.order_quantum) > 1 && Number(item.quantity) % Number(item.order_quantum) !== 0

const breakingBatchOf = ref<BatchedItem | null>(null)
const breakBatchQuantity = ref<number | null>(null)

function openBreakBatch(item: BatchedItem) {
	breakingBatchOf.value = item
	breakBatchQuantity.value = Number(item.quantity)
}

function breakBatch() {
	if (!breakingBatchOf.value || !breakBatchQuantity.value) {
		return
	}
	router.patch(
		route("grp.org.procurement.org_partners.show.shopping_list.update", [
			routeParams["organisation"],
			props.orgPartner.id,
			breakingBatchOf.value.id,
		]),
		{ quantity: breakBatchQuantity.value, break_batch: true },
		{
			preserveScroll: true,
			...tableLoadingEvents,
			onSuccess: () => (breakingBatchOf.value = null),
		}
	)
}

const isEditable = (item: { is_editable?: boolean }) => !!item.is_editable

const pokingId = ref<number | null>(null)
const pokedIds = ref<Record<number, boolean>>({})

async function pokePartner(item: { id: number; org_stock_code: string }) {
	pokingId.value = item.id
	try {
		const response = await axios.post(
			route("grp.org.procurement.org_partners.show.shopping_list.poke", [
				routeParams["organisation"],
				props.orgPartner.id,
				item.id,
			])
		)
		pokedIds.value[item.id] = true
		notify({
			title: ctrans(":partner poked about :code", { partner: props.partnerCode, code: item.org_stock_code }),
			text: Number(response.data)
				? ctrans(":count people in production were notified", { count: String(response.data) })
				: ctrans("Nobody in the partner production could be notified"),
			type: Number(response.data) ? "success" : "warning",
		})
	} catch (error: any) {
		if (error?.response?.status === 429) {
			pokedIds.value[item.id] = true
		}
		notify({
			title: ctrans("Could not poke the partner"),
			text: error?.response?.data?.message || ctrans("Something went wrong"),
			type: "error",
		})
	} finally {
		pokingId.value = null
	}
}

const typedSkos = ref<Record<number, number>>({})
const savingId = ref<number | null>(null)
const linesTotal = ref(props.linesValue)
const savedQuantities = ref<Record<number, number>>({})
watch(
	() => props.data,
	() => {
		savedQuantities.value = {}
		linesTotal.value = props.linesValue
	}
)

const withSavedQuantity = <T extends QuantityItem>(item: T): T => {
	const quantity = savedQuantities.value[item.id]

	return quantity === undefined
		? item
		: { ...item, quantity, quantity_ordered: quantity * (Number(item.units_per_pack) || 1) }
}

interface QuantityItem {
	id: number
	quantity: number | string
	quantity_ordered: number
	units_per_pack: number
	price_per_sko: number | null
}

async function saveQuantity(item: QuantityItem, quantity: number): Promise<boolean> {
	savingId.value = item.id
	try {
		const response = await axios.patch(
			route("grp.org.procurement.org_partners.show.shopping_list.update", [
				routeParams["organisation"],
				props.orgPartner.id,
				item.id,
			]),
			{ quantity }
		)
		const savedQuantity = Number(response.data?.quantity ?? quantity)
		const previousQuantity = Number(withSavedQuantity(item).quantity)
		linesTotal.value += (savedQuantity - previousQuantity) * Number(item.price_per_sko ?? 0)
		savedQuantities.value[item.id] = savedQuantity
		delete typedSkos.value[item.id]

		return true
	} catch (error: any) {
		notify({
			title: ctrans("Something went wrong"),
			text: error?.response?.data?.message || ctrans("Failed to update quantity"),
			type: "error",
		})

		return false
	} finally {
		savingId.value = null
	}
}

async function onSaveQuantity(item: QuantityItem, form: { quantity: number; defaults: () => void }) {
	if (await saveQuantity(item, Number(form.quantity))) {
		form.defaults()
	}
}

function confirmStopSuggesting(event: MouseEvent, item: { id: number; org_stock_code: string }) {
	confirm.require({
		target: event.currentTarget as HTMLElement,
		message: ctrans(
			"Remove :code and never suggest it again? You can unblock it later from the Blocked tab.",
			{ code: item.org_stock_code }
		),
		icon: "pi pi-exclamation-triangle",
		acceptLabel: ctrans("Don't suggest again"),
		rejectLabel: ctrans("Cancel"),
		acceptClass: "p-button-danger",
		rejectClass: "p-button-text",
		accept: () => deleteItem(item, true),
	})
}

const isSubmitting = ref(false)

const isSubmitConfirmOpen = ref(false)

function confirmSubmit() {
	if (isSubmitConfirmOpen.value) {
		return
	}
	isSubmitConfirmOpen.value = true
	confirm.require({
		group: "partner-shopping-list",
		header: ctrans("Submit the ongoing PO"),
		message: ctrans(
			"Send the :count draft lines to the partner? They see them and start working on them straight away.",
			{ count: String(props.draftsCount) }
		),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Submit") },
		reject: () => (isSubmitConfirmOpen.value = false),
		onHide: () => (isSubmitConfirmOpen.value = false),
		accept: () => {
			isSubmitConfirmOpen.value = false
			router.post(
				route("grp.org.procurement.org_partners.show.shopping_list.submit", [
					routeParams["organisation"],
					props.orgPartner.id,
				]),
				{},
				{
					preserveScroll: true,
					onStart: () => (isSubmitting.value = true),
					onFinish: () => (isSubmitting.value = false),
					onSuccess: () =>
						notify({
							title: ctrans("Submitted"),
							text: ctrans("The partner can now see the lines"),
							type: "success",
						}),
					onError: (errors) =>
						notify({
							title: ctrans("Nothing submitted"),
							text: errors.submit ?? ctrans("Something went wrong, please try again"),
							type: "error",
						}),
				}
			)
		},
	})
}

const submittingId = ref<number | null>(null)

const hasUnsavedQuantity = (item: { id: number; quantity: number | string }) =>
	typedSkos.value[item.id] !== undefined && typedSkos.value[item.id] !== Number(withSavedQuantity(item).quantity)

function submitItem(item: { id: number }) {
	router.post(
		route("grp.org.procurement.org_partners.show.shopping_list.submit_item", [
			routeParams["organisation"],
			props.orgPartner.id,
			item.id,
		]),
		{},
		{
			preserveScroll: true,
			onStart: () => (submittingId.value = item.id),
			onFinish: () => (submittingId.value = null),
		}
	)
}

function deleteItem(item: { id: number }, stopSuggesting = false) {
	router.delete(
		route("grp.org.procurement.org_partners.show.shopping_list.destroy", [
			routeParams["organisation"],
			props.orgPartner.id,
			item.id,
		]),
		{ preserveScroll: true, data: stopSuggesting ? { stop_suggesting: true } : {}, ...tableLoadingEvents }
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template v-if="!isSentView" #otherBefore>
			<Button
				type="negative"
				icon="fal fa-trash-alt"
				:label="ctrans('Delete all')"
				@click="confirmDeleteAll" />
			<Button
				v-if="hubSuggestionsCount"
				type="secondary"
				icon="fal fa-industry-alt"
				:label="ctrans('Drop :partner suggestions (:count)', { partner: partnerCode, count: String(hubSuggestionsCount) })"
				:tooltip="ctrans(':partner production added these for you. Keep them by submitting, or drop them all here', { partner: partnerCode })"
				@click="confirmDropHubSuggestions" />
			<Button
				type="secondary"
				icon="fal fa-magic"
				:label="ctrans('Auto-fill')"
				@click="isAutoFillOpen = true" />
			<Button
				type="secondary"
				icon="fal fa-upload"
				:label="ctrans('Upload')"
				@click="isUploadOpen = true" />
			<Button type="create" :label="ctrans('Add stocks')" @click="isModalOpen = true" />
			<Button
				type="primary"
				icon="fal fa-paper-plane"
				:label="ctrans('Submit (:count)', { count: String(draftsCount) })"
				:tooltip="ctrans('Send the draft lines to the partner, they do not see them until then')"
				:disabled="!draftsCount"
				:loading="isSubmitting"
				@click="confirmSubmit" />
		</template>
	</PageHeading>

	<ConfirmDialog group="partner-shopping-list" />
	<ConfirmPopup />
	<UploadExcel
		v-model="isUploadOpen"
		:title="upload_excel.title"
		:progressDescription="upload_excel.progressDescription"
		:upload_spreadsheet="upload_excel.upload_spreadsheet"
		:preview_template="upload_excel.preview_template"
		:propsRefreshAfterFinish="['data']" />
	<ModalPartnerStockList v-model="isModalOpen" :fetchRoute="orgStockFetchRoute" />
	<ModalAutoFillShoppingList
		v-model="isAutoFillOpen"
		:orgPartnerId="orgPartner.id"
		:currency="orgPartner.currency" />

	<div class="mt-5 flex items-center justify-between gap-4 px-4">
		<span class="text-sm text-gray-600">
			{{
				isSentView
					? ctrans("What the partner is working on. Change the order on the Ongoing PO.")
					: ctrans("Not sent yet: the partner sees these lines after you press Submit.")
			}}
			<span class="ml-2 font-semibold tabular-nums text-gray-900">{{
				useLocaleStore().currencyFormat(orgPartner.currency, linesTotal)
			}}</span>
		</span>
	</div>
	<div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 px-4 text-sm">
		<div
			v-for="group in filterGroups.filter((group) => group.options.length)"
			:key="group.key"
			class="flex flex-wrap items-center gap-1.5">
			<span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ group.label }}</span>
			<button
				v-for="option in group.options"
				:key="option.value"
				type="button"
				class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
				:class="selectedFilters(group.key).includes(option.value)
					? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
					: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
				@click="toggleFilter(group.key, option.value)">
				<span>{{ option.label }}</span>
				<span
					class="rounded-full px-1.5 text-xs tabular-nums"
					:class="selectedFilters(group.key).includes(option.value) ? 'bg-white/20' : 'bg-white text-gray-500'">
					{{ option.count }}
				</span>
			</button>
		</div>
	</div>
	<div class="mt-2 h-[3px]">
		<ProgressBar v-if="isTableLoading" mode="indeterminate" style="height: 3px" />
	</div>
	<Table :resource="data">
		<template #cell(info)="{ item }">
			<div class="flex items-start gap-3">
				<div class="h-20 w-20 shrink-0 rounded">
					<Image :src="item.image_sources" />
				</div>
				<div class="min-w-0 space-y-0.5">
					<div class="text-sm font-medium text-gray-800">
						{{ item.org_stock_name }}
						<span
							v-if="item.suggested_by_hub"
							v-tooltip="ctrans(':partner production suggested this line', { partner: partnerCode })"
							class="ml-1 whitespace-nowrap rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-xs font-normal text-indigo-700">
							<FontAwesomeIcon icon="fal fa-industry-alt" fixed-width aria-hidden="true" />
							{{ ctrans(":partner suggested", { partner: partnerCode }) }}
						</span>
					</div>
					<div v-if="item.price_per_sko" class="text-xs text-gray-500">
						{{ ctrans("SKO cost") }}:
						{{ useLocaleStore().currencyFormat(orgPartner.currency, item.price_per_sko) }}
						<span v-if="Number(item.units_per_pack) > 1">
							· {{ ctrans("1 SKO = :units units", { units: Number(item.units_per_pack) }) }}
						</span>
					</div>
					<div class="flex flex-wrap gap-x-3 text-xs text-gray-500">
						<span v-if="item.their_available !== null && item.their_available !== undefined">
							{{ ctrans("Partner stock") }}:
							<span class="font-semibold text-gray-800">{{ useLocaleStore().number(Number(item.their_available)) }}</span>
						</span>
						<span v-if="Number(item.order_quantum) > 1">
							{{ ctrans("Batch") }}:
							<span class="font-semibold text-gray-800">{{ useLocaleStore().number(Number(item.order_quantum)) }}</span>
							{{ ctrans("SKOs") }}
						</span>
						<span v-if="Number(item.skos_per_carton) > 0">
							{{ ctrans("Carton") }}:
							<span class="font-semibold text-gray-800">{{ useLocaleStore().number(Number(item.skos_per_carton)) }}</span>
							{{ ctrans("SKOs") }}
						</span>
					</div>
					<RenderWhenVisible minHeight="9rem">
						<PurchaseOrderItemStockInfo
							:item="withSavedQuantity(item)"
							isPartner
							:typedSkosById="typedSkos"
							:isOrderLocked="!isEditable(item)" />
					</RenderWhenVisible>
				</div>
			</div>
		</template>
		<template #cell(quantity)="{ item }">
			<RenderWhenVisible v-if="isEditable(item)" minHeight="4.5rem">
				<div class="flex flex-col items-end">
					<PurchaseOrderSuggestButton
						class="mb-1"
						:item="withSavedQuantity(item)"
						isPartner
						:typedSkosById="typedSkos"
						@suggest="(skos) => saveQuantity(item, skos)" />
					<NumberWithButtonSave
						:key="`${item.id}-${withSavedQuantity(item).quantity}`"
						isWithRefreshModel
						:modelValue="Number(withSavedQuantity(item).quantity)"
						:min="0"
						:isLoading="savingId === item.id"
						@update:modelValue="(value) => (typedSkos[item.id] = Number(value))"
						@onSave="(form) => onSaveQuantity(item, form)" />
					<span
						v-if="Number(item.order_quantum) > 1"
						v-tooltip="
							ctrans('Made in batches: ordered in multiples of :quantum SKOs', {
								quantum: item.order_quantum,
							})
						"
						class="mt-0.5 cursor-help text-xs"
						:class="isPartBatch(item) ? 'font-medium text-red-600' : 'text-gray-400'">
						{{ isPartBatch(item) ? ctrans("Part batch") : "×" + item.order_quantum }}
					</span>
					<Button
						v-if="item.state === 'draft'"
						class="mt-1"
						type="secondary"
						size="xs"
						icon="fal fa-paper-plane"
						:label="ctrans('Submit')"
						:loading="submittingId === item.id"
						:disabled="hasUnsavedQuantity(item)"
						:tooltip="hasUnsavedQuantity(item) ? ctrans('Save the quantity first') : ctrans('Send only this line to the partner now')"
						@click="submitItem(item)" />
				</div>
			</RenderWhenVisible>
			<span v-else class="block text-right font-medium tabular-nums">{{
				useLocaleStore().number(Number(item.quantity))
			}}</span>
		</template>
		<template #cell(amount)="{ item }">
			<span class="block text-right tabular-nums">
				{{
					item.price_per_sko
						? useLocaleStore().currencyFormat(orgPartner.currency, amountOf(withSavedQuantity(item)))
						: "-"
				}}
			</span>
		</template>
		<template #cell(priority)="{ item }">
			<select
				v-if="isEditable(item)"
				:value="item.priority"
				class="rounded border-gray-300 py-0.5 pl-2 pr-7 text-xs"
				:class="{
					'text-red-600': item.priority === 'urgent',
					'text-amber-600': item.priority === 'high',
					'text-gray-400': item.priority === 'low',
				}"
				@change="
					updateItem(item, { priority: ($event.target as HTMLSelectElement).value })
				">
				<option v-for="priority in priorities" :key="priority" :value="priority">
					{{ ctrans(priority) }}
				</option>
			</select>
			<span v-else>{{ ctrans(item.priority) }}</span>
		</template>
		<template #cell(progress)="{ item }">
			<div class="flex flex-col gap-2">
				<div v-for="(part, index) in item.progress_parts ?? [item.progress]" :key="index" class="space-y-0.5">
					<div class="flex items-center gap-1.5">
						<span v-if="item.progress_parts" class="text-xs tabular-nums text-gray-500">{{
							useLocaleStore().number(part.quantity)
						}}</span>
						<span
							class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs"
							:class="{
								'border-gray-200 bg-gray-50 text-gray-500': part?.tone === 'gray',
								'border-amber-200 bg-amber-50 text-amber-700': part?.tone === 'amber',
								'border-indigo-200 bg-indigo-50 text-indigo-700': part?.tone === 'indigo',
								'border-emerald-200 bg-emerald-50 text-emerald-700': part?.tone === 'emerald',
								'border-red-200 bg-red-50 text-red-700': part?.tone === 'red',
							}">
							{{ part?.label }}
						</span>
						<span v-if="part?.reference" class="font-mono text-xs text-gray-400">{{ part.reference }}</span>
					</div>
					<div v-for="(detail, detailIndex) in part?.details ?? []" :key="detailIndex" class="flex gap-1.5 text-xxs text-gray-500">
						<span>{{ detail.label }}</span>
						<span v-if="detail.at" class="text-gray-400">{{ useFormatTime(detail.at, { formatTime: "dd MMM HH:mm" }) }}</span>
					</div>
				</div>
			</div>
		</template>
		<template #cell(created_at)="{ item }">
			{{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}
			<span v-if="item.added_by_name" class="text-gray-400">· {{ item.added_by_name }}</span>
		</template>
		<template #cell(actions)="{ item }">
			<Button
				v-if="item.can_be_poked"
				icon="fal fa-bells"
				:tooltip="
					pokedIds[item.id] || item.is_recently_poked
						? ctrans('Poked :at, you can poke again an hour later', { at: useFormatTime(item.poked_at ?? new Date(), { formatTime: 'dd MMM HH:mm' }) })
						: ctrans('Poke the partner: tell their production you urgently need this')
				"
				type="tertiary"
				size="xs"
				class="mr-1"
				:loading="pokingId === item.id"
				:disabled="pokedIds[item.id] || item.is_recently_poked"
				@click="pokePartner(item)" />
			<div class="flex flex-wrap justify-end gap-1">
				<Button
					v-if="
						isEditable(item) && Number(item.order_quantum) > 1
					"
					icon="fal fa-cut"
					:tooltip="ctrans('Break batch: order a quantity that is not whole batches')"
					type="tertiary"
					size="xs"
					@click="openBreakBatch(item)" />
				<Button
					v-if="isEditable(item)"
					icon="fal fa-ban"
					:tooltip="ctrans('Remove and never suggest this product again')"
					type="tertiary"
					size="xs"
					@click="confirmStopSuggesting($event, item)" />
				<Button
					v-if="isEditable(item)"
					:label="ctrans('Remove')"
					icon="fal fa-trash-alt"
					:tooltip="isSentView ? ctrans('Withdraw from the partner, they have not started it yet') : ctrans('Remove from the ongoing PO')"
					type="delete"
					size="xs"
					@click="deleteItem(item)" />
			</div>
		</template>
	</Table>

	<Modal :isOpen="!!breakingBatchOf" width="w-full max-w-md" @onClose="breakingBatchOf = null">
		<form
			v-if="breakingBatchOf"
			class="space-y-4 px-2 py-2 text-sm text-gray-700"
			@submit.prevent="breakBatch">
			<div>
				<div class="font-semibold text-gray-900">
					{{
						ctrans("Break the batch of :code", { code: breakingBatchOf.org_stock_code })
					}}
				</div>
				<p class="mt-1 text-gray-500">
					{{
						ctrans(
							"The hub makes :code in whole batches, :quantum SKOs at a time. Any other quantity leaves the hub making a full batch and carrying the rest.",
							{
								code: breakingBatchOf.org_stock_code,
								quantum: breakingBatchOf.order_quantum,
							}
						)
					}}
				</p>
			</div>
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Quantity (SKO)") }}</span>
				<input
					v-model.number="breakBatchQuantity"
					type="number"
					min="1"
					step="1"
					inputmode="numeric"
					class="mt-1 w-full rounded-md border-gray-300 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500" />
			</label>
			<div class="flex justify-end gap-2">
				<Button
					:label="ctrans('Cancel')"
					type="tertiary"
					size="s"
					@click="breakingBatchOf = null" />
				<Button
					:label="ctrans('Break batch')"
					type="negative"
					icon="fal fa-cut"
					size="s"
					nativeType="submit"
					:disabled="
						!breakBatchQuantity || breakBatchQuantity < 1
					" />
			</div>
		</form>
	</Modal>
</template>
