<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
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
import { faCut, faUpload } from "@fal"
library.add(faCut, faUpload)
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import ConfirmDialog from "primevue/confirmdialog"
import ProgressBar from "primevue/progressbar"
import { useConfirm } from "primevue/useconfirm"

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: object
	orgPartner: { id: number; slug: string; currency: string }
	orgStockFetchRoute: { name: string; parameters: object }
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
const routeParams = route().params

function confirmDeleteAll() {
	confirm.require({
		group: "partner-shopping-list",
		header: ctrans("Delete all open items"),
		message: ctrans(
			"Remove every open item from this shopping list? Items already taken by the partner are kept."
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
const breakBatchUnderstood = ref(false)

function openBreakBatch(item: BatchedItem) {
	breakingBatchOf.value = item
	breakBatchQuantity.value = Number(item.quantity)
	breakBatchUnderstood.value = false
}

function breakBatch() {
	if (!breakingBatchOf.value || !breakBatchQuantity.value || !breakBatchUnderstood.value) {
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

function deleteItem(item: { id: number }) {
	router.delete(
		route("grp.org.procurement.org_partners.show.shopping_list.destroy", [
			routeParams["organisation"],
			props.orgPartner.id,
			item.id,
		]),
		{ preserveScroll: true, ...tableLoadingEvents }
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #otherBefore>
			<Button
				type="negative"
				icon="fal fa-trash-alt"
				:label="ctrans('Delete all')"
				@click="confirmDeleteAll" />
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
		</template>
	</PageHeading>

	<ConfirmDialog group="partner-shopping-list" />
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

	<div class="mt-5 h-[3px]">
		<ProgressBar v-if="isTableLoading" mode="indeterminate" style="height: 3px" />
	</div>
	<Table :resource="data">
		<template #cell(info)="{ item }">
			<div class="flex items-start gap-3">
				<div class="h-12 w-12 shrink-0 rounded">
					<Image :src="item.image_sources" />
				</div>
				<div class="min-w-0 text-xs leading-5">
					<div class="truncate text-sm font-medium text-gray-800">
						{{ item.org_stock_name }}
					</div>
					<div class="text-gray-500">
						<span
							v-tooltip="
								ctrans('Stock the partner has available to sell us right now')
							"
							class="cursor-help underline decoration-dotted"
							>{{ ctrans("Their stock") }}</span
						>
						<b class="font-medium text-gray-700 tabular-nums">{{
							item.their_available !== null
								? useLocaleStore().number(Math.floor(Number(item.their_available)))
								: "-"
						}}</b>
						·
						<span
							v-tooltip="ctrans('Stock available in our own warehouse')"
							class="cursor-help underline decoration-dotted"
							>{{ ctrans("our stock") }}</span
						>
						<b class="font-medium text-gray-700 tabular-nums">{{
							useLocaleStore().number(Math.floor(Number(item.buyer_available ?? 0)))
						}}</b>
						<template v-if="item.days_of_cover !== null">
							·
							<span
								:class="{
									'text-red-600 font-medium': Number(item.days_of_cover) <= 14,
									'text-amber-600':
										Number(item.days_of_cover) > 14 &&
										Number(item.days_of_cover) <= 30,
								}">
								{{
									Number(item.days_of_cover) === 0
										? ctrans("we run out now")
										: `${ctrans("Estimated: Would run out in")} ~${Math.round(Number(item.days_of_cover))} ${ctrans("days")}`
								}}
							</span>
						</template>
					</div>
				</div>
			</div>
		</template>
		<template #cell(quantity)="{ item }">
			<span class="block text-right font-medium tabular-nums">{{
				useLocaleStore().number(Number(item.quantity))
			}}</span>
			<span
				v-if="Number(item.order_quantum) > 1"
				v-tooltip="
					ctrans('Made in batches: ordered in multiples of :quantum SKOs', {
						quantum: item.order_quantum,
					})
				"
				class="block cursor-help text-right text-xs"
				:class="isPartBatch(item) ? 'font-medium text-red-600' : 'text-gray-400'">
				{{ isPartBatch(item) ? ctrans("Part batch") : "×" + item.order_quantum }}
			</span>
		</template>
		<template #cell(amount)="{ item }">
			<span class="block text-right tabular-nums">
				{{
					item.price_per_sko
						? useLocaleStore().currencyFormat(orgPartner.currency, amountOf(item))
						: "-"
				}}
			</span>
		</template>
		<template #cell(priority)="{ item }">
			<select
				v-if="item.state === 'open' && !item.pre_picked_at"
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
			<div class="flex items-center gap-1.5">
				<span
					class="whitespace-nowrap rounded-full border px-2 py-0.5 text-xs"
					:class="{
						'border-gray-200 bg-gray-50 text-gray-500': item.progress?.tone === 'gray',
						'border-amber-200 bg-amber-50 text-amber-700':
							item.progress?.tone === 'amber',
						'border-indigo-200 bg-indigo-50 text-indigo-700':
							item.progress?.tone === 'indigo',
						'border-emerald-200 bg-emerald-50 text-emerald-700':
							item.progress?.tone === 'emerald',
					}">
					{{ item.progress?.label }}
				</span>
				<span v-if="item.progress?.reference" class="font-mono text-xs text-gray-400">{{
					item.progress.reference
				}}</span>
			</div>
		</template>
		<template #cell(created_at)="{ item }">
			{{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}
			<span v-if="item.added_by_name" class="text-gray-400">· {{ item.added_by_name }}</span>
		</template>
		<template #cell(actions)="{ item }">
			<Button
				v-if="
					item.state === 'open' && !item.pre_picked_at && Number(item.order_quantum) > 1
				"
				icon="fal fa-cut"
				:tooltip="ctrans('Break batch: order a quantity that is not whole batches')"
				type="tertiary"
				size="xs"
				class="mr-1"
				@click="openBreakBatch(item)" />
			<Button
				v-if="item.state === 'open' && !item.pre_picked_at"
				icon="fal fa-trash-alt"
				:tooltip="ctrans('Remove from the shopping list')"
				type="negative"
				size="xs"
				@click="deleteItem(item)" />
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
			<label class="flex cursor-pointer items-start gap-2">
				<input
					v-model="breakBatchUnderstood"
					type="checkbox"
					class="mt-0.5 rounded border-gray-300" />
				<span>{{
					ctrans(
						"I understand this breaks a production batch and I really need this quantity"
					)
				}}</span>
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
						!breakBatchQuantity || breakBatchQuantity < 1 || !breakBatchUnderstood
					" />
			</div>
		</form>
	</Modal>
</template>
