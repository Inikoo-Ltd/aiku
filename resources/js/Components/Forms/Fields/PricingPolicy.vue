<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { InputNumber } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
	form: any
	fieldName: string
	options?: any
	submit?: Function
	fieldData?: {
		currency_code?: string
		currency_symbol?: string
		example_price?: number
		value_field?: string
	}
}>()

const valueField = computed(() => props.fieldData?.value_field ?? "pricing_value")
const currencySymbol = computed(() => props.fieldData?.currency_symbol || "£")
const examplePrice = computed(() => props.fieldData?.example_price ?? 100)

const mode = computed(() => props.form[props.fieldName] || "percent")
const amount = computed(() => Number(props.form[valueField.value]) || 0)

const examplePriceAfter = computed(() =>
	mode.value === "percent"
		? Math.round(examplePrice.value * (1 + amount.value / 100) * 100) / 100
		: Math.round((examplePrice.value + amount.value) * 100) / 100
)

const money = (value: number) =>
	`${currencySymbol.value}${value.toLocaleString(undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	})}`

const priceColor = computed(() => {
	if (examplePriceAfter.value <= 0) return "text-red-600"
	const diff = ((examplePriceAfter.value - examplePrice.value) / examplePrice.value) * 100
	if (diff > 20 || diff < -20) return "text-red-600"
	if (diff > 15 || diff < -15) return "text-orange-500"
	if (diff > 10 || diff < -10) return "text-yellow-600"
	return "text-emerald-700"
})

const switchMode = (newMode: "percent" | "fixed" | "not_follow") => {
	if (mode.value === newMode) return
	props.form[valueField.value] = 0
	props.form[props.fieldName] = newMode
}
</script>

<template>
	<div>
		<div class="flex flex-row flex-wrap items-center gap-2">
			<Button
				:key="'mode-percent-' + mode"
				:label="trans('± % over live RRP')"
				size="xs"
				:type="mode === 'percent' ? 'primary' : 'tertiary'"
				:style="mode === 'percent' ? undefined : 'white-w-outline'"
				@click="switchMode('percent')" />
			<Button
				:key="'mode-fixed-' + mode"
				:label="trans('± :currency over live RRP', { currency: currencySymbol })"
				size="xs"
				:type="mode === 'fixed' ? 'primary' : 'tertiary'"
				:style="mode === 'fixed' ? undefined : 'white-w-outline'"
				@click="switchMode('fixed')" />
			<Button
				:key="'mode-notfollow-' + mode"
				:label="trans('Do not follow RRP')"
				size="xs"
				:type="mode === 'not_follow' ? 'primary' : 'tertiary'"
				:style="mode === 'not_follow' ? undefined : 'white-w-outline'"
				@click="switchMode('not_follow')" />
		</div>

		<div v-if="mode === 'not_follow'" class="mt-3 min-h-[56px] flex items-center text-sm text-gray-500">
			{{ trans("You set your prices directly on eBay. We will never upload or overwrite them.") }}
		</div>

		<div v-else class="mt-3 min-h-[56px] flex flex-row items-center gap-4">
			<InputNumber
				@update:modelValue="(value) => (form[valueField] = value)"
				@input="(event) => (form[valueField] = event.value)"
				:modelValue="amount"
				:inputClass="'xxs w-[100px]'"
				:min="-100"
				:max="900"
				:minFractionDigits="0"
				:maxFractionDigits="2"
				:allowEmpty="false"
				:suffix="mode === 'percent' ? '%' : undefined"
				:prefix="mode === 'fixed' ? currencySymbol : amount > 0 ? '+' : undefined"
				size="small" />

			<div class="flex flex-row items-center gap-3 whitespace-nowrap">
				<div>
					<div class="text-[10px] uppercase tracking-wide text-gray-400">
						{{ trans("Example RRP") }}
					</div>
					<div class="text-base text-gray-500">{{ money(examplePrice) }}</div>
				</div>
				<div class="text-gray-400">→</div>
				<div>
					<div class="text-[10px] uppercase tracking-wide" :class="priceColor">
						{{ trans("eBay price") }}
					</div>
					<div class="text-base font-semibold" :class="priceColor">
						{{ money(examplePriceAfter) }}
					</div>
				</div>
			</div>
		</div>

		<div v-if="mode !== 'not_follow'" class="text-xs text-gray-500">
			{{ trans("Every product in this channel is priced this way, unless you give it its own price.") }}
		</div>

		<label v-if="mode !== 'not_follow'" class="mt-2 flex items-start gap-2 cursor-pointer select-none">
			<input
				type="checkbox"
				class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500"
				:checked="!!form['pricing_reset_all']"
				@change="(event) => (form['pricing_reset_all'] = (event.target as HTMLInputElement).checked)" />
			<span class="text-xs" :class="form['pricing_reset_all'] ? 'text-red-600' : 'text-gray-500'">
				{{ trans("Also reset products that have their own price, so every single product follows this policy. Their prices will be overwritten by this rule.") }}
			</span>
		</label>
	</div>
</template>
