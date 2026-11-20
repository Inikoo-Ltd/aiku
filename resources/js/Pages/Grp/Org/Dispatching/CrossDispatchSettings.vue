<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import ToggleSwitch from "primevue/toggleswitch"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faPencil, faTruckContainer } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faTruckContainer)

type Direction = {
	id: number
	is_origin: boolean
	origin_name: string
	dispatcher_name: string
	our_enabled: boolean
	their_enabled: boolean
	is_on: boolean
	goods_price_basis: string
	dispatch_fee_per_parcel: number
	currency_code: string
	shipping_pass_through: string
	settlement_period: string
	allow_customs_crossing: boolean
	tax_treatment: string
	update_route: { name: string; parameters: (string | number)[] }
}

const props = defineProps<{
	title: string
	pageHead: object
	data: object
	history: object
	options: Record<string, string[]>
}>()

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const optionLabels = computed<Record<string, string>>(() => ({
	dispatcher_cost: ctrans("Dispatching company's cost"),
	at_cost: ctrans("At cost"),
	weekly: ctrans("Weekly"),
	monthly: ctrans("Monthly"),
	quarterly: ctrans("Quarterly"),
	origin_shop_rules: ctrans("Shop's own rules, unchanged"),
}))
const labelOf = (value: string) => optionLabels.value[value] ?? value
const selectOptions = (key: string) => (props.options[key] ?? []).map((value) => ({ value, label: labelOf(value) }))

const savingId = ref<number | null>(null)
const rowErrors = ref<Record<number, string>>({})

const firstError = (errors: Record<string, string>) => Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")

function toggle(direction: Direction, enabled: boolean) {
	router.patch(
		route(direction.update_route.name, direction.update_route.parameters),
		{ enabled },
		{
			preserveScroll: true,
			onStart: () => {
				savingId.value = direction.id
				delete rowErrors.value[direction.id]
			},
			onFinish: () => (savingId.value = null),
			onError: (errors: Record<string, string>) => (rowErrors.value[direction.id] = firstError(errors)),
		}
	)
}

const editing = ref<Direction | null>(null)
const isDialogVisible = computed({
	get: () => editing.value !== null,
	set: (visible: boolean) => {
		if (!visible) {
			editing.value = null
		}
	},
})
const form = ref({
	goods_price_basis: "",
	dispatch_fee_per_parcel: 0 as number | null,
	shipping_pass_through: "",
	settlement_period: "",
	allow_customs_crossing: false,
	tax_treatment: "",
})
const isSaving = ref(false)
const dialogError = ref<string | null>(null)

function edit(direction: Direction) {
	dialogError.value = null
	form.value = {
		goods_price_basis: direction.goods_price_basis,
		dispatch_fee_per_parcel: direction.dispatch_fee_per_parcel,
		shipping_pass_through: direction.shipping_pass_through,
		settlement_period: direction.settlement_period,
		allow_customs_crossing: direction.allow_customs_crossing,
		tax_treatment: direction.tax_treatment,
	}
	editing.value = direction
}

function save() {
	if (!editing.value) {
		return
	}

	router.patch(
		route(editing.value.update_route.name, editing.value.update_route.parameters),
		{ ...form.value, dispatch_fee_per_parcel: form.value.dispatch_fee_per_parcel ?? 0 },
		{
			preserveScroll: true,
			onStart: () => {
				isSaving.value = true
				dialogError.value = null
			},
			onFinish: () => (isSaving.value = false),
			onSuccess: () => (editing.value = null),
			onError: (errors: Record<string, string>) => (dialogError.value = firstError(errors)),
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<ul class="mx-4 mt-4 list-disc space-y-1 pl-5 text-sm text-gray-600">
		<li>{{ ctrans("A dropshipping order can be dispatched by another company's warehouse only when both companies allow that direction. Each company switches its own side.") }}</li>
		<li>{{ ctrans("The delivery note then belongs to the dispatching company: its warehouse counters and reports include these orders, and the ordering company's admins and accountants keep the order and the invoice but no longer open the delivery note.") }}</li>
		<li>{{ ctrans("A change applies only to orders dispatched after it. Every change is recorded with who made it, in the history below.") }}</li>
	</ul>

	<Table :resource="data" class="mt-4">
		<template #cell(origin_name)="{ item: direction }">
			<span :class="direction.is_origin ? 'font-medium' : ''">{{ direction.origin_name }}</span>
			<span v-if="direction.is_origin" class="ml-1 text-xs text-gray-500">({{ ctrans("us") }})</span>
		</template>

		<template #cell(dispatcher_name)="{ item: direction }">
			<span :class="direction.is_origin ? '' : 'font-medium'">{{ direction.dispatcher_name }}</span>
			<span v-if="!direction.is_origin" class="ml-1 text-xs text-gray-500">({{ ctrans("us") }})</span>
		</template>

		<template #cell(our_enabled)="{ item: direction }">
			<ToggleSwitch
				:modelValue="direction.our_enabled"
				:disabled="savingId === direction.id"
				:aria-label="ctrans('Allow :origin orders to be dispatched by :dispatcher', { origin: direction.origin_name, dispatcher: direction.dispatcher_name })"
				@update:modelValue="(enabled: boolean) => toggle(direction, enabled)"
			/>
			<div v-if="rowErrors[direction.id]" class="mt-1 text-xs text-red-500">{{ rowErrors[direction.id] }}</div>
		</template>

		<template #cell(their_enabled)="{ item: direction }">
			<span class="whitespace-nowrap">
				<FontAwesomeIcon :icon="direction.their_enabled ? faCheck : faTimes" fixed-width aria-hidden="true" :class="direction.their_enabled ? 'text-green-600' : 'text-gray-400'" />
				{{ direction.their_enabled ? ctrans("Yes") : ctrans("Not yet") }}
			</span>
		</template>

		<template #cell(is_on)="{ item: direction }">
			<span class="whitespace-nowrap rounded px-2 py-0.5 text-xs font-medium" :class="direction.is_on ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
				{{ direction.is_on ? ctrans("On") : ctrans("Off") }}
			</span>
		</template>

		<template #cell(goods_price_basis)="{ item: direction }">{{ labelOf(direction.goods_price_basis) }}</template>

		<template #cell(dispatch_fee_per_parcel)="{ item: direction }">
			<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(direction.currency_code, direction.dispatch_fee_per_parcel) }}</span>
		</template>

		<template #cell(shipping_pass_through)="{ item: direction }">{{ labelOf(direction.shipping_pass_through) }}</template>

		<template #cell(settlement_period)="{ item: direction }">{{ labelOf(direction.settlement_period) }}</template>

		<template #cell(allow_customs_crossing)="{ item: direction }">
			<span class="whitespace-nowrap">{{ direction.allow_customs_crossing ? ctrans("May cross") : ctrans("Never crosses") }}</span>
		</template>

		<template #cell(actions)="{ item: direction }">
			<Button type="tertiary" size="xs" :label="ctrans('Terms')" @click="edit(direction)">
				<template #icon>
					<FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" class="text-gray-500" />
				</template>
			</Button>
		</template>
	</Table>

	<h2 class="mx-4 mt-8 text-sm font-semibold text-gray-600">{{ ctrans("History of changes") }}</h2>
	<TableHistories :data="history" tab="history" />

	<Dialog
		v-model:visible="isDialogVisible"
		modal
		:header="editing ? ctrans(':origin orders dispatched by :dispatcher', { origin: editing.origin_name, dispatcher: editing.dispatcher_name }) : ''"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div v-if="editing" class="space-y-4 text-sm">
			<label class="block">
				<span class="mb-1 block text-gray-700">{{ ctrans("Goods charged at") }} <span class="text-red-500">*</span></span>
				<Select v-model="form.goods_price_basis" :options="selectOptions('goods_price_basis')" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
			</label>

			<label class="block">
				<span class="mb-1 block text-gray-700">{{ ctrans("Dispatch fee per parcel") }} <span class="text-red-500">*</span></span>
				<InputNumber v-model="form.dispatch_fee_per_parcel" v-bind="locale.currencyInputOptions(editing.currency_code)" :min="0" :class="fieldFocusClass" fluid />
				<span class="mt-1 block text-xs text-gray-500">{{ ctrans("In the dispatching company's currency, as it issues the invoice.") }}</span>
			</label>

			<label class="block">
				<span class="mb-1 block text-gray-700">{{ ctrans("Shipping charged") }} <span class="text-red-500">*</span></span>
				<Select v-model="form.shipping_pass_through" :options="selectOptions('shipping_pass_through')" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
			</label>

			<label class="block">
				<span class="mb-1 block text-gray-700">{{ ctrans("Invoiced between the companies") }} <span class="text-red-500">*</span></span>
				<Select v-model="form.settlement_period" :options="selectOptions('settlement_period')" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
			</label>

			<label class="block">
				<span class="mb-1 block text-gray-700">{{ ctrans("Tax on the customer's invoice") }} <span class="text-red-500">*</span></span>
				<Select v-model="form.tax_treatment" :options="selectOptions('tax_treatment')" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
			</label>

			<div class="flex items-start gap-3">
				<ToggleSwitch v-model="form.allow_customs_crossing" inputId="cross-dispatch-customs" />
				<label for="cross-dispatch-customs" class="text-gray-700">
					{{ ctrans("Parcels may cross the UK / EU customs border") }}
					<span class="mt-0.5 block text-xs text-gray-500">{{ ctrans("Leave off until customs paperwork for these parcels exists.") }}</span>
				</label>
			</div>

			<div v-if="dialogError" class="text-xs text-red-500">{{ dialogError }}</div>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="editing = null">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" class="text-red-500" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Save terms')" :loading="isSaving" @click="save">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
