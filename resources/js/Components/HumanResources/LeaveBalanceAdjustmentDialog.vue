<script setup lang="ts">
import { computed, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { fieldFocusClass } from "@/Composables/useHrFormControls"

library.add(faTimes, faCheck)

type Figures = { annual_used: number; medical_used: number; unpaid_used: number }

export interface LeaveBalanceRow {
	id: number
	employee_contract_id: number | null
	period_start: string | null
	period_end: string | null
	annual_allowance: number | null
	annual_used: number
	medical_used: number
	unpaid_used: number
	annual_remaining: number | null
	annual_adjustment: number
	medical_adjustment: number
	unpaid_adjustment: number
	recorded: Figures | null
}

const props = defineProps<{
	balance: LeaveBalanceRow | null
}>()

const visible = defineModel<boolean>("visible", { default: false })

const form = useForm({
	annual_adjustment: 0 as number | null,
	medical_adjustment: 0 as number | null,
	unpaid_adjustment: 0 as number | null,
})

const counters = computed(() => [
	{ field: "annual_adjustment" as const, used: "annual_used" as const, label: ctrans("Annual") },
	{ field: "medical_adjustment" as const, used: "medical_used" as const, label: ctrans("Medical") },
	{ field: "unpaid_adjustment" as const, used: "unpaid_used" as const, label: ctrans("Unpaid") },
])

watch(visible, (isVisible) => {
	if (!isVisible || !props.balance) {
		return
	}

	form.clearErrors()
	form.annual_adjustment = Number(props.balance.annual_adjustment)
	form.medical_adjustment = Number(props.balance.medical_adjustment)
	form.unpaid_adjustment = Number(props.balance.unpaid_adjustment)
})

const days = (value: number | null | undefined): string => String(Number(value ?? 0))

const recorded = (key: keyof Figures): number => Number(props.balance?.recorded?.[key] ?? 0)

const resulting = (field: "annual_adjustment" | "medical_adjustment" | "unpaid_adjustment", key: keyof Figures): number =>
	Math.round((recorded(key) + Number(form[field] ?? 0)) * 100) / 100

const resultClass = (field: "annual_adjustment" | "medical_adjustment" | "unpaid_adjustment", key: keyof Figures): string => {
	const difference = resulting(field, key) - Number(props.balance?.[key] ?? 0)

	if (difference > 0) {
		return "text-red-600"
	}

	return difference < 0 ? "text-green-600" : "text-gray-900"
}

const periodLabel = computed(() => {
	if (!props.balance?.period_start) {
		return props.balance?.employee_contract_id ? ctrans("Contract balance without a period") : ctrans("Balance without a contract")
	}

	return `${useFormatTime(props.balance.period_start)} – ${props.balance.period_end ? useFormatTime(props.balance.period_end) : ctrans("open-ended")}`
})

const submit = () => {
	if (!props.balance) {
		return
	}

	form
		.transform((data) => ({
			annual_adjustment: data.annual_adjustment ?? 0,
			medical_adjustment: data.medical_adjustment ?? 0,
			unpaid_adjustment: data.unpaid_adjustment ?? 0,
		}))
		.patch(route("grp.models.employee.leave_balance.adjustment", { balance: props.balance.id }), {
			preserveScroll: true,
			onSuccess: () => {
				visible.value = false
			},
		})
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:draggable="false"
		:header="ctrans('Opening adjustment')"
		:style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }">
		<form v-if="balance" class="space-y-4" @submit.prevent="submit">
			<div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
				<div class="font-medium tabular-nums text-gray-900">{{ periodLabel }}</div>
				<div class="text-gray-600">
					{{ ctrans("Days taken that are not recorded as leave requests in Aiku. Days used = opening adjustment + approved leaves.") }}
				</div>
			</div>

			<div class="space-y-3">
				<div v-for="counter in counters" :key="counter.field" class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
					<div>
						<label :for="`balance-${counter.field}`" class="mb-1 block text-sm font-medium text-gray-700">{{ counter.label }}</label>
						<InputNumber
							v-model="form[counter.field]"
							:inputId="`balance-${counter.field}`"
							:min="0"
							:max="366"
							:step="0.5"
							:minFractionDigits="0"
							:maxFractionDigits="1"
							:suffix="` ${ctrans('days')}`"
							showButtons
							fluid
							:invalid="!!form.errors[counter.field]"
							:class="fieldFocusClass" />
						<p v-if="form.errors[counter.field]" class="mt-1 text-sm text-red-500">{{ form.errors[counter.field] }}</p>
					</div>
					<div class="text-sm tabular-nums text-gray-600 sm:pb-2 sm:text-right">
						+ {{ days(recorded(counter.used)) }} {{ ctrans("recorded") }} =
						<span class="font-semibold" :class="resultClass(counter.field, counter.used)">{{ days(resulting(counter.field, counter.used)) }}</span>
						<span class="text-gray-400"> ({{ ctrans("now :days", { days: days(balance[counter.used]) }) }})</span>
					</div>
				</div>
			</div>

			<div class="flex justify-end gap-2 pt-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click.prevent="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" nativeType="submit" :label="ctrans('Save')" :loading="form.processing">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>
</template>
