<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-15 14:35
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faInfoCircle, faExclamationTriangle, faBox, faCube, faChevronRight } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type AddableItem = {
	id: number
	purchase_order_id: number
	purchase_order_reference: string
	supplier: string | null
	code: string | null
	name: string | null
	quantity_ordered: number
	quantity_awaiting_delivery: number
	units_per_sko: number
	units_per_carton: number
}

type QuantityMode = "sko" | "units"

type PurchaseOrderGroup = {
	id: number
	reference: string
	supplier: string | null
	items: AddableItem[]
}

const props = defineProps<{
	data: { route: routeType; from_agent_warehouse?: boolean; items: AddableItem[] } | null | undefined
	purchaseOrders?: { id: number; in_this_container: boolean }[]
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const search = ref("")
const mode = ref<QuantityMode>("sko")
const quantities = ref<Record<number, number | null>>({})
const expandedPurchaseOrderIds = ref<number[]>([])
const isSubmitting = ref(false)
const isListingRulesOpen = ref(false)

const modeOptions = computed(() => [
	{ label: ctrans("SKO"), value: "sko" as QuantityMode, icon: faBox },
	{ label: ctrans("Units"), value: "units" as QuantityMode, icon: faCube },
])

watch(visible, (isVisible) => {
	if (isVisible) {
		search.value = ""
		quantities.value = {}
		expandedPurchaseOrderIds.value = []
	}
})

const items = computed(() => props.data?.items ?? [])

watch(() => props.data, (data) => {
	if (data && data.items.length === 0) {
		isListingRulesOpen.value = true
	}
}, { immediate: true })

const listingRules = computed(() => [
	ctrans("Confirmed purchase orders of this agent, in this container's currency."),
	ctrans("Only units not yet in a container."),
	...(props.data?.from_agent_warehouse
		? [ctrans("Only units received at the agent's warehouse, rejects excluded.")]
		: []),
	ctrans("Purchase orders on an Aurora container are not listed."),
	ctrans("Packed or other stages do not matter."),
])

const filteredItems = computed(() => {
	const term = search.value.trim().toLowerCase()

	return term
		? items.value.filter(item => [item.code, item.name, item.purchase_order_reference, item.supplier]
			.some(value => value?.toLowerCase().includes(term)))
		: items.value
})

const purchaseOrders = computed(() => {
	const groups = new Map<number, PurchaseOrderGroup>()

	for (const item of filteredItems.value) {
		if (!groups.has(item.purchase_order_id)) {
			groups.set(item.purchase_order_id, {
				id: item.purchase_order_id,
				reference: item.purchase_order_reference,
				supplier: item.supplier,
				items: [],
			})
		}
		groups.get(item.purchase_order_id)!.items.push(item)
	}

	return [...groups.values()]
})

const itemsById = computed(() => new Map(items.value.map(item => [item.id, item])))

const purchaseOrderIdsInThisContainer = computed(() => new Set((props.purchaseOrders ?? []).filter(purchaseOrder => purchaseOrder.in_this_container).map(purchaseOrder => purchaseOrder.id)))

const isSearching = computed(() => search.value.trim() !== "")

const isExpanded = (purchaseOrder: PurchaseOrderGroup) => isSearching.value || expandedPurchaseOrderIds.value.includes(purchaseOrder.id)

const toggleExpanded = (purchaseOrder: PurchaseOrderGroup) => {
	expandedPurchaseOrderIds.value = expandedPurchaseOrderIds.value.includes(purchaseOrder.id)
		? expandedPurchaseOrderIds.value.filter(id => id !== purchaseOrder.id)
		: [...expandedPurchaseOrderIds.value, purchaseOrder.id]
}

const unitsPerSko = (item: AddableItem) => Number(item.units_per_sko) || 1

const toUnits = (item: AddableItem, quantity: number) => mode.value === "sko" ? quantity * unitsPerSko(item) : quantity

const fromUnits = (item: AddableItem, units: number, quantityMode: QuantityMode = mode.value) => quantityMode === "sko" ? units / unitsPerSko(item) : units

const round = (value: number) => Math.round(value * 1000) / 1000

const fullQuantity = (item: AddableItem, quantityMode: QuantityMode = mode.value) => round(fromUnits(item, item.quantity_awaiting_delivery, quantityMode))

const formatNumber = (value: number) => locale.number(round(value))

const isSelected = (item: AddableItem) => item.id in quantities.value

const toggleItem = (item: AddableItem) => {
	const next = { ...quantities.value }

	if (isSelected(item)) {
		delete next[item.id]
	} else {
		next[item.id] = fullQuantity(item)
	}

	quantities.value = next
}

const isPurchaseOrderSelected = (purchaseOrder: PurchaseOrderGroup) => purchaseOrder.items.every(isSelected)

const numberSelectedOf = (purchaseOrder: PurchaseOrderGroup) => purchaseOrder.items.filter(isSelected).length

const selectItems = (itemsToChange: AddableItem[], select: boolean) => {
	const next = { ...quantities.value }

	for (const item of itemsToChange) {
		if (select && !(item.id in next)) {
			next[item.id] = fullQuantity(item)
		} else if (!select) {
			delete next[item.id]
		}
	}

	quantities.value = next
}

const togglePurchaseOrder = (purchaseOrder: PurchaseOrderGroup) => selectItems(purchaseOrder.items, !isPurchaseOrderSelected(purchaseOrder))

const areAllFilteredSelected = computed(() => filteredItems.value.length > 0 && filteredItems.value.every(isSelected))

const toggleAllFiltered = () => selectItems(filteredItems.value, !areAllFilteredSelected.value)

watch(mode, (newMode, oldMode) => {
	const next: Record<number, number | null> = {}

	for (const [id, quantity] of Object.entries(quantities.value)) {
		const item = itemsById.value.get(Number(id))
		if (!item || quantity === null) {
			next[Number(id)] = quantity
			continue
		}
		if (quantity === fullQuantity(item, oldMode)) {
			next[Number(id)] = fullQuantity(item, newMode)
			continue
		}
		const units = oldMode === "sko" ? quantity * unitsPerSko(item) : quantity
		next[Number(id)] = round(newMode === "sko" ? units / unitsPerSko(item) : units)
	}

	quantities.value = next
})

const fillAll = (item: AddableItem) => {
	quantities.value = { ...quantities.value, [item.id]: fullQuantity(item) }
}

const unitsOf = (item: AddableItem) => {
	const quantity = quantities.value[item.id]

	if (!quantity) {
		return 0
	}

	return quantity === fullQuantity(item) ? round(item.quantity_awaiting_delivery) : round(toUnits(item, quantity))
}

const breakdown = (item: AddableItem, units: number) => {
	const parts = [
		ctrans(":count units", { count: formatNumber(units) }),
		ctrans(":count SKO", { count: formatNumber(units / unitsPerSko(item)) }),
	]

	if (Number(item.units_per_carton) > 0) {
		parts.push(ctrans(":count cartons", { count: formatNumber(units / Number(item.units_per_carton)) }))
	}

	return parts.join(" · ")
}

const packing = (item: AddableItem) => {
	const sko = ctrans("1 SKO = :units units", { units: formatNumber(unitsPerSko(item)) })

	if (!(Number(item.units_per_carton) > 0)) {
		return sko
	}

	return `${sko} · ${ctrans("1 carton = :skos SKO (:units units)", {
		skos: formatNumber(Number(item.units_per_carton) / unitsPerSko(item)),
		units: formatNumber(Number(item.units_per_carton)),
	})}`
}

const problem = (item: AddableItem): { tone: "red" | "amber"; text: string } | null => {
	const quantity = quantities.value[item.id]
	if (!quantity || quantity <= 0) {
		return { tone: "red", text: ctrans("Enter a quantity") }
	}

	const units = unitsOf(item)
	if (units > round(item.quantity_awaiting_delivery)) {
		return { tone: "red", text: ctrans("Only :awaiting are awaiting delivery", { awaiting: breakdown(item, item.quantity_awaiting_delivery) }) }
	}

	if (Math.abs(units - Math.round(units)) > 0.001) {
		return { tone: "amber", text: ctrans("This is not a whole number of units, check the SKO quantity") }
	}

	const skos = units / unitsPerSko(item)
	if (Math.abs(skos - Math.round(skos)) > 0.001) {
		const wholeSkos = Math.floor(skos)
		return {
			tone: "amber",
			text: ctrans("Not a whole number of SKOs: :skos SKO and :units loose units", {
				skos: formatNumber(wholeSkos),
				units: formatNumber(units - wholeSkos * unitsPerSko(item)),
			}),
		}
	}

	return null
}

const selectedItems = computed(() => Object.keys(quantities.value).map(id => itemsById.value.get(Number(id))).filter((item): item is AddableItem => !!item))

const hasBlockingProblem = computed(() => selectedItems.value.some(item => problem(item)?.tone === "red"))

const totalUnits = computed(() => selectedItems.value.reduce((sum, item) => sum + unitsOf(item), 0))

const totalSkos = computed(() => selectedItems.value.reduce((sum, item) => sum + unitsOf(item) / unitsPerSko(item), 0))

const submit = () => {
	if (!props.data || selectedItems.value.length === 0 || hasBlockingProblem.value) {
		return
	}

	const addedCount = selectedItems.value.length

	router.post(route(props.data.route.name, props.data.route.parameters), {
		items: selectedItems.value.map(item => ({
			purchase_order_transaction_id: item.id,
			unit_quantity: unitsOf(item),
		})),
	}, {
		preserveScroll: true,
		onStart: () => { isSubmitting.value = true },
		onSuccess: () => {
			visible.value = false
			notify({
				title: ctrans("Items added"),
				text: addedCount === 1 ? ctrans("1 item added to this container") : ctrans(":count items added to this container", { count: addedCount }),
				type: "success",
			})
		},
		onFinish: () => { isSubmitting.value = false },
		onError: (errors: Record<string, string>) => {
			notify({
				title: ctrans("Failed to add items"),
				text: Object.values(errors)[0] ?? ctrans("Something went wrong"),
				type: "error",
			})
		},
	})
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="ctrans('Add to this container')"
		:style="{ width: '60rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="mb-3 flex gap-2 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] p-3 text-sm text-gray-700">
			<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-[--app-accent]" fixed-width aria-hidden="true" />
			<div class="space-y-1">
				<p>
					<span class="font-semibold">{{ ctrans("Unit") }}</span>: {{ ctrans("one single piece, the smallest thing we count.") }}
					<span class="font-semibold">{{ ctrans("SKO") }}</span>: {{ ctrans("the pack we store and sell, holding several units. Each line says how many.") }}
				</p>
				<p>{{ ctrans("Tick a purchase order to add everything still awaiting delivery on it, or open it to pick single items and how many of each. Choose whether you type the quantity in SKOs or in units. Every line shows the result in units, SKOs and cartons before you add it, and what is not added stays on the purchase order for another container.") }}</p>
				<button
					type="button"
					class="flex items-center gap-1 font-semibold text-[--app-accent] hover:text-[--app-accent-strong]"
					:aria-expanded="isListingRulesOpen"
					@click="isListingRulesOpen = !isListingRulesOpen"
				>
					<FontAwesomeIcon
						:icon="faChevronRight"
						class="transition-transform"
						:class="isListingRulesOpen ? 'rotate-90' : ''"
						fixed-width
						aria-hidden="true"
					/>
					{{ ctrans("Which items are listed") }}
				</button>
				<ul v-if="isListingRulesOpen" class="list-disc space-y-0.5 pl-9">
					<li v-for="rule in listingRules" :key="rule">{{ rule }}</li>
				</ul>
			</div>
		</div>

		<div class="mb-3 flex flex-wrap items-center gap-3">
			<InputText v-model="search" class="min-w-[14rem] flex-1" :placeholder="ctrans('Search code, name, purchase order or supplier')" />
			<label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
				<Checkbox :modelValue="areAllFilteredSelected" binary :disabled="filteredItems.length === 0" @update:modelValue="toggleAllFiltered" />
				{{ ctrans("Select all") }}
			</label>
			<div class="flex items-center gap-2 text-sm text-gray-600">
				{{ ctrans("Type quantities in") }}
				<SegmentedToggle v-model="mode" :options="modeOptions" :ariaLabel="ctrans('Type quantities in')" />
			</div>
		</div>

		<div v-if="!data" class="space-y-2">
			<div v-for="index in 4" :key="index" class="h-14 animate-pulse rounded-lg bg-gray-100" />
		</div>

		<div v-else class="max-h-[55vh] overflow-y-auto rounded-lg border border-gray-200">
			<div v-for="purchaseOrder in purchaseOrders" :key="purchaseOrder.id" class="border-b border-gray-200 last:border-b-0">
				<div class="sticky top-0 z-10 flex items-center gap-3 bg-gray-50 px-3 py-2 text-sm">
					<Checkbox
						:modelValue="isPurchaseOrderSelected(purchaseOrder)"
						:indeterminate="numberSelectedOf(purchaseOrder) > 0 && !isPurchaseOrderSelected(purchaseOrder)"
						binary
						:aria-label="ctrans('Select whole purchase order')"
						@update:modelValue="togglePurchaseOrder(purchaseOrder)"
					/>
					<button
						type="button"
						class="flex min-w-0 flex-1 items-center gap-3 text-left"
						:aria-expanded="isExpanded(purchaseOrder)"
						@click="toggleExpanded(purchaseOrder)"
					>
						<FontAwesomeIcon
							:icon="faChevronRight"
							class="text-gray-400 transition-transform"
							:class="isExpanded(purchaseOrder) ? 'rotate-90' : ''"
							fixed-width
							aria-hidden="true"
						/>
						<span class="whitespace-nowrap font-semibold text-gray-800">{{ purchaseOrder.reference }}</span>
						<span v-if="purchaseOrderIdsInThisContainer.has(purchaseOrder.id)" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
							{{ ctrans("Partly in this container") }}
						</span>
						<span class="truncate text-gray-500">{{ purchaseOrder.supplier }}</span>
						<span class="ml-auto whitespace-nowrap text-xs text-gray-500">
							{{ numberSelectedOf(purchaseOrder) > 0
								? ctrans(":selected of :count items selected", { selected: numberSelectedOf(purchaseOrder), count: purchaseOrder.items.length })
								: ctransChoice(":count item awaiting delivery|:count items awaiting delivery", purchaseOrder.items.length) }}
						</span>
					</button>
				</div>

				<div
					v-for="item in isExpanded(purchaseOrder) ? purchaseOrder.items : []"
					:key="item.id"
					class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 px-3 py-2.5 sm:grid-cols-[auto_1fr_auto]"
					:class="isSelected(item) ? 'bg-[--app-accent-soft]' : ''"
				>
					<Checkbox :modelValue="isSelected(item)" binary class="mt-0.5" @update:modelValue="toggleItem(item)" />
					<div class="min-w-0 text-sm">
						<div class="font-medium text-gray-800">
							{{ item.code ?? "-" }} <span class="font-normal text-gray-600">{{ item.name }}</span>
						</div>
						<div class="text-xs text-gray-500">{{ packing(item) }}</div>
						<div class="text-xs text-gray-700">
							{{ ctrans("Awaiting delivery") }}: <span class="font-medium">{{ breakdown(item, item.quantity_awaiting_delivery) }}</span>
							<span v-if="round(item.quantity_awaiting_delivery) < round(item.quantity_ordered)" class="text-gray-500">
								({{ ctrans("of :ordered units ordered, the rest is already in a container", { ordered: formatNumber(item.quantity_ordered) }) }})
							</span>
						</div>
					</div>

					<div v-if="isSelected(item)" class="col-span-2 flex flex-col items-end gap-1 sm:col-span-1">
						<div class="flex items-center gap-2">
							<InputNumber
								v-model="quantities[item.id]"
								:min="0"
								:maxFractionDigits="3"
								:suffix="mode === 'sko' ? ` ${ctrans('SKO')}` : ` ${ctrans('units')}`"
								inputClass="!w-32 !py-1 text-right"
								:aria-label="ctrans('Quantity')"
							/>
							<Button type="tertiary" size="xs" :label="ctrans('All')" @click="fillAll(item)" />
						</div>
						<div class="text-xs text-gray-600">= {{ breakdown(item, unitsOf(item)) }}</div>
						<div
							v-if="problem(item)"
							class="flex items-center gap-1 text-xs"
							:class="problem(item)?.tone === 'red' ? 'text-red-600' : 'text-amber-600'"
						>
							<FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
							{{ problem(item)?.text }}
						</div>
					</div>
				</div>
			</div>

			<p v-if="purchaseOrders.length === 0" class="p-4 text-center text-sm text-gray-500">
				{{ items.length === 0 ? ctrans("No items of this agent are waiting for a container") : ctrans("No items match your search") }}
			</p>
		</div>

		<template #footer>
			<div class="flex w-full flex-wrap items-center justify-end gap-3">
				<span v-if="selectedItems.length" class="mr-auto text-sm text-gray-600">
					{{ ctrans(":items items · :units units · :skos SKO", {
						items: selectedItems.length,
						units: formatNumber(totalUnits),
						skos: formatNumber(totalSkos),
					}) }}
				</span>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					type="tertiary"
					:label="selectedItems.length === 1 ? ctrans('Add 1 item') : ctrans('Add :count items', { count: selectedItems.length })"
					:loading="isSubmitting"
					:disabled="selectedItems.length === 0 || hasBlockingProblem"
					@click="submit"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
