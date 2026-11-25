<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 25 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxCheck, faTimes, faInfoCircle } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type RouteDef = { name: string; parameters: (string | number)[] }
type Supplier = { id: number; code: string; name: string; purchase_orders: number }
type Line = { id: number; code: string; name: string | null; supplier_code: string | null; skos_awaiting: number }
type PurchaseOrder = {
	id: number
	reference: string
	needs_agreeing: boolean
	lines: Line[]
	open_deliveries: { reference: string; route: RouteDef }[]
}

const props = defineProps<{
	routes: { options: RouteDef; receive: RouteDef }
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const suppliers = ref<Supplier[]>([])
const supplierId = ref<number | null>(null)
const purchaseOrders = ref<PurchaseOrder[]>([])
const skos = ref<Record<number, number | null>>({})
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)

async function load(params: Record<string, number> = {}) {
	isLoading.value = true
	error.value = null
	try {
		const response = await axios.get(route(props.routes.options.name, props.routes.options.parameters), { params })
		return response.data
	} catch {
		error.value = ctrans("Could not load the open orders, try again")
		return {}
	} finally {
		isLoading.value = false
	}
}

watch(visible, async (isVisible) => {
	if (!isVisible) {
		return
	}
	supplierId.value = null
	purchaseOrders.value = []
	skos.value = {}
	suppliers.value = (await load()).suppliers ?? []
})

watch(supplierId, async (id) => {
	purchaseOrders.value = []
	skos.value = {}
	if (id) {
		const loaded = (await load({ org_supplier_id: id })).purchase_orders ?? []
		if (supplierId.value === id) {
			purchaseOrders.value = loaded
		}
	}
})

const allLines = computed(() => purchaseOrders.value.flatMap((purchaseOrder) => purchaseOrder.lines))
const linesArrived = computed(() => allLines.value.filter((line) => (skos.value[line.id] ?? 0) > 0))
const ordersToAgree = computed(() => purchaseOrders.value.filter((purchaseOrder) => purchaseOrder.needs_agreeing && purchaseOrder.lines.some((line) => (skos.value[line.id] ?? 0) > 0)))
const canSubmit = computed(() => linesArrived.value.length > 0 && !isSaving.value)

function submit() {
	if (!canSubmit.value) {
		return
	}

	router.post(
		route(props.routes.receive.name, props.routes.receive.parameters),
		{ org_supplier_id: supplierId.value, skos: Object.fromEntries(linesArrived.value.map((line) => [line.id, skos.value[line.id]])) },
		{
			onStart: () => {
				isSaving.value = true
				error.value = null
			},
			onFinish: () => (isSaving.value = false),
			onSuccess: () => (visible.value = false),
			onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Receive a supplier parcel')" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="space-y-4 text-sm">
			<label class="block space-y-1">
				<span class="text-xs font-medium text-gray-600">{{ ctrans("Supplier") }} <span class="text-red-500">*</span></span>
				<Select
					v-model="supplierId"
					:options="suppliers"
					optionValue="id"
					:optionLabel="(supplier: Supplier) => `${supplier.name} (${supplier.code})`"
					:placeholder="ctrans('Who sent the parcel?')"
					filter
					:loading="isLoading && !suppliers.length"
					:class="fieldFocusClass"
					fluid
				/>
			</label>

			<div v-if="isLoading && supplierId" class="flex justify-center py-6"><LoadingIcon /></div>

			<template v-else-if="supplierId">
				<p v-if="!purchaseOrders.length" class="text-gray-500">{{ ctrans("This supplier has nothing awaiting delivery") }}</p>

				<div v-else class="max-h-[50vh] space-y-4 overflow-y-auto">
					<p class="text-xs text-gray-500">{{ ctrans("Enter the SKOs in the parcel. Lines left empty stay open on their order.") }}</p>

					<div v-for="purchaseOrder in purchaseOrders" :key="purchaseOrder.id" class="space-y-2">
						<div class="flex items-baseline gap-2">
							<span class="whitespace-nowrap font-semibold text-gray-800">{{ purchaseOrder.reference }}</span>
							<span v-if="purchaseOrder.needs_agreeing" class="text-xs text-amber-700">{{ ctrans("not agreed yet: receiving agrees it") }}</span>
						</div>

						<p v-for="delivery in purchaseOrder.open_deliveries" :key="delivery.reference" class="flex gap-2 text-xs text-gray-600">
							<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
							<span>
								{{ ctrans("Part of this order is already on delivery") }}
								<Link :href="route(delivery.route.name, delivery.route.parameters)" class="whitespace-nowrap font-medium text-gray-800 underline">{{ delivery.reference }}</Link>:
								{{ ctrans("receive those goods there") }}
							</span>
						</p>

						<div v-for="line in purchaseOrder.lines" :key="line.id" class="flex items-center gap-3">
							<div class="min-w-0 flex-1">
								<span class="whitespace-nowrap font-medium text-gray-800">{{ line.code }}</span>
								<span v-if="line.name" class="ml-1 text-xs text-gray-500">{{ line.name }}</span>
								<span class="block text-xs text-gray-500">{{ ctransChoice(":skos SKO awaited|:skos SKOs awaited", line.skos_awaiting, { skos: locale.number(line.skos_awaiting) }) }}</span>
							</div>
							<InputNumber
								v-model="skos[line.id]"
								:min="0"
								:max="line.skos_awaiting"
								:maxFractionDigits="3"
								:placeholder="'0'"
								inputClass="w-24 text-right"
								:class="fieldFocusClass"
							/>
						</div>
					</div>
				</div>

				<p v-if="ordersToAgree.length" class="text-xs text-amber-700">
					{{ ctrans("Receiving agrees :orders with the supplier", { orders: ordersToAgree.map((purchaseOrder) => purchaseOrder.reference).join(", ") }) }}
				</p>
			</template>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Receive parcel')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faBoxCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
