<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-21 15:29
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faPlus } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Container = {
	id: number
	reference: string
	date: string | null
	number_purchase_orders: number
}

type Item = {
	id: number
	code: string
	name?: string | null
	image_thumbnail?: object | null
	agent_warehouse: {
		quantity_awaiting_delivery: number
		quantity_packed_not_on_container: number
		containers_route: routeType | null
		new_container_route: routeType | null
		organisation_code: string | null
		is_qc_approved: boolean
	}
}

const props = defineProps<{
	items: Item[]
}>()

const emit = defineEmits<{
	(e: "added"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const containers = ref<Container[]>([])
const isFetching = ref(false)
const hasFetched = ref(false)
const selectedContainerId = ref<number | null>(null)
const isNewContainer = ref(false)
const quantities = ref<Record<number, number | null>>({})
const isSaving = ref(false)

const warehouse = computed(() => props.items[0]?.agent_warehouse)
const organisationCode = computed(() => warehouse.value?.organisation_code ?? "")
const isBulk = computed(() => props.items.length > 1)

const awaiting = (item: Item) => item.agent_warehouse.quantity_awaiting_delivery
const notPackedYet = (item: Item) =>
	Math.max(0, Math.round(((quantities.value[item.id] ?? 0) - item.agent_warehouse.quantity_packed_not_on_container) * 1000) / 1000)
const defaultQuantity = (item: Item) => {
	const packed = item.agent_warehouse.quantity_packed_not_on_container
	return packed > 0 ? Math.min(packed, awaiting(item)) : awaiting(item)
}

const containerOptions = computed(() =>
	containers.value.map((container) => ({
		id: container.id,
		label: `${container.reference} (${ctrans(":count purchase orders", { count: container.number_purchase_orders })})`,
	}))
)

function chooseNewContainer() {
	isNewContainer.value = true
	selectedContainerId.value = null
}

watch(selectedContainerId, (containerId) => {
	if (containerId) {
		isNewContainer.value = false
	}
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

async function fetchContainers(search?: string) {
	const containersRoute = warehouse.value?.containers_route
	if (!containersRoute) {
		return
	}
	isFetching.value = true
	try {
		const response = await axios.get(route(containersRoute.name, containersRoute.parameters), { params: { search: search || undefined } })
		containers.value = response.data.data
		hasFetched.value = true
		if (!search && !containers.value.length && warehouse.value?.new_container_route && !selectedContainerId.value) {
			isNewContainer.value = true
		}
	} catch (error: any) {
		notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? "", type: "error" })
	} finally {
		isFetching.value = false
	}
}

function onFilter(event: { value: string }) {
	if (searchTimer) {
		clearTimeout(searchTimer)
	}
	searchTimer = setTimeout(() => fetchContainers(event.value), 300)
}

watch(
	[visible, () => props.items.map((item) => item.id).join(",")],
	([isVisible]) => {
		if (!isVisible) {
			return
		}
		selectedContainerId.value = null
		isNewContainer.value = false
		containers.value = []
		hasFetched.value = false
		quantities.value = Object.fromEntries(props.items.map((item) => [item.id, defaultQuantity(item)]))
		fetchContainers()
	},
	{ immediate: true }
)

const itemsToAdd = computed(() =>
	props.items
		.filter((item) => (quantities.value[item.id] ?? 0) > 0)
		.map((item) => ({ purchase_order_transaction_id: item.id, unit_quantity: quantities.value[item.id] as number }))
)

const hasQuantityOverAwaiting = computed(() => props.items.some((item) => (quantities.value[item.id] ?? 0) > awaiting(item)))

const canSubmit = computed(() => (isNewContainer.value || !!selectedContainerId.value) && itemsToAdd.value.length > 0 && !hasQuantityOverAwaiting.value)

function submit() {
	const newContainerRoute = warehouse.value?.new_container_route
	if (!canSubmit.value || (isNewContainer.value && !newContainerRoute)) {
		return
	}

	const addedCount = itemsToAdd.value.length

	router.post(
		isNewContainer.value && newContainerRoute
			? route(newContainerRoute.name, newContainerRoute.parameters)
			: route("grp.models.stock-delivery.purchase_order_items.add", { stockDelivery: selectedContainerId.value }),
		{ items: itemsToAdd.value },
		{
			preserveScroll: true,
			onStart: () => (isSaving.value = true),
			onFinish: () => (isSaving.value = false),
			onSuccess: () => {
				visible.value = false
				emit("added")
				notify({ title: ctrans("Success"), text: ctransChoice(":count item added to the container|:count items added to the container", addedCount), type: "success" })
			},
			onError: (errors: Record<string, string>) =>
				notify({ title: ctrans("Something went wrong"), text: Object.values(errors ?? {})[0], type: "error" }),
		}
	)
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="isBulk ? ctrans('Add :count items to a shipment', { count: items.length }) : ctrans('Add :code to a shipment', { code: items[0]?.code })"
		:style="{ width: isBulk ? '36rem' : '28rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="space-y-4 text-sm">
			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Shipment") }}</span>
				<div v-if="isNewContainer" class="flex items-center gap-2 rounded-md border border-[--app-accent-muted] bg-[--app-accent-soft] px-3 py-2">
					<FontAwesomeIcon :icon="faPlus" class="text-[--app-accent]" fixed-width aria-hidden="true" />
					<span class="text-gray-700">{{ ctrans("A new shipment for :organisation will be opened", { organisation: organisationCode }) }}</span>
				</div>
				<Select
					v-else
					v-model="selectedContainerId"
					:options="containerOptions"
					optionLabel="label"
					optionValue="id"
					filter
					:filterPlaceholder="ctrans('Search by reference')"
					:loading="isFetching"
					:placeholder="ctrans('Choose a shipment in process')"
					:emptyMessage="isFetching ? ctrans('Loading…') : ctrans('No shipment in process for :organisation yet', { organisation: organisationCode })"
					:emptyFilterMessage="isFetching ? ctrans('Loading…') : ctrans('No shipment found')"
					:class="fieldFocusClass"
					fluid
					@filter="onFilter"
				/>
				<span v-if="hasFetched && !containers.length" class="block text-xs text-gray-500">
					{{ ctrans("No shipment in process for :organisation yet, shipments of other clients are not listed", { organisation: organisationCode }) }}
				</span>
				<button
					v-if="warehouse?.new_container_route && !isNewContainer"
					type="button"
					class="text-xs text-[--app-accent] underline-offset-2 hover:underline"
					@click="chooseNewContainer"
				>
					{{ ctrans("Or open a new shipment for :organisation", { organisation: organisationCode }) }}
				</button>
				<button
					v-else-if="isNewContainer && containers.length"
					type="button"
					class="text-xs text-[--app-accent] underline-offset-2 hover:underline"
					@click="isNewContainer = false"
				>
					{{ ctrans("Or choose a shipment in process") }}
				</button>
			</div>

			<p v-if="warehouse && !warehouse.is_qc_approved" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				<span>{{ ctrans("The QC of this order is not approved yet. The goods can still go in a shipment, but they will leave without a QC approval unless it is recorded before the shipment is dispatched.") }}</span>
			</p>

			<div class="max-h-[50vh] space-y-3 overflow-y-auto">
				<div v-for="item in items" :key="item.id" class="flex items-start gap-3">
					<Image :src="item.image_thumbnail" imageCover class="h-14 w-14 shrink-0 overflow-hidden rounded border border-gray-200" />
					<div class="min-w-0 flex-1 space-y-1">
						<div>
							<span class="whitespace-nowrap font-medium text-gray-800">{{ item.code }}</span>
							<span v-if="item.name" class="ml-1 text-xs text-gray-500">{{ item.name }}</span>
						</div>
						<InputNumber v-model="quantities[item.id]" :min="0" :max="awaiting(item)" :maxFractionDigits="3" :suffix="` ${ctrans('units')}`" :class="fieldFocusClass" fluid />
						<span class="block text-xs text-gray-500">
							{{ ctrans(":units units not in a shipment yet, :packed of them packed", { units: locale.number(awaiting(item)), packed: locale.number(Math.min(item.agent_warehouse.quantity_packed_not_on_container, awaiting(item))) }) }}
						</span>
						<p v-if="notPackedYet(item) > 0" class="flex gap-1.5 text-xs text-amber-700">
							<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
							<span>{{ ctrans(":units of these units are not packed yet", { units: locale.number(notPackedYet(item)) }) }}</span>
						</p>
					</div>
				</div>
			</div>
		</div>

		<template #footer>
			<Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
				<template #icon>
					<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button type="secondary" full :label="ctrans('Add to shipment')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
				<template #icon>
					<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</Dialog>
</template>
