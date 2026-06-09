<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot, faPlus, faMinus, faUndoAlt } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputNumber from "primevue/inputnumber"
import { inject, ref, watch } from "vue"
import { faSave as fadSave } from "@fad"
import { faSave as falSave, faInfoCircle } from "@fal"
import { faAsterisk, faQuestion, faSpinner, faMinus as fasMinus, faPlus as fasPlus } from "@fas"
import { useForm } from "@inertiajs/vue3"
import LoadingIcon from "./Utils/LoadingIcon.vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { debounce } from 'lodash-es'

library.add(
	faRobot,
	faPlus,
	faMinus,
	faUndoAlt,
	faAsterisk,
	faQuestion,
	falSave,
	faInfoCircle,
	fadSave,
	faSpinner,
	fasMinus,
	fasPlus
)

const props = defineProps<{
	modelValue: number
	min?: number
	max?: number
	saveOnForm?: boolean
	routeSubmit?: routeType
	allowZero?: boolean
	noUndoButton?: boolean
	noSaveButton?: boolean
	keySubmit?: string
	bindToTarget?: {
		max?: number
		min?: number
		step?: number
	}
	colorTheme?: string // '#374151'
	isUseAxios?: boolean
	parentClass?: string
	isLoading?: boolean
	readonly?: boolean
	additionalData?: {
		[key: string]: any
	}
	autoSave?: boolean
	isWithRefreshModel?: boolean
	denominator?: number
	disableInput?: boolean
}>()

const emits = defineEmits<{
	(e: "onSave", value: string | number): void
	(e: "update:modelValue", value: number, saveFunction: Function): void
	(e: "onSuccess", newValue: number, oldValue: number): void
	(e: "onError", value: {}): void
}>()

const model = defineModel()

const roundToDecimals = (value: number, decimals: number = 2): number => {
	return Number(Number(value).toFixed(decimals))
}

const form = useForm({
	quantity: roundToDecimals(props.modelValue),
})
const formDefaultValue = ref({
	quantity: roundToDecimals(props.modelValue),
})

const onSaveViaForm = async () => {
	console.log("ewqewqewq")
	if (!props.routeSubmit?.name) return

	if (props.isUseAxios) {
		try {
			form.processing = true
			await axios[props.routeSubmit?.method || "post"](
				route(props.routeSubmit?.name, props.routeSubmit?.parameters),
				{
					[props.keySubmit || "quantity"]: form.quantity,
				}
			)

			form.defaults("quantity", roundToDecimals(form.quantity))
			emits("onSuccess", form.quantity, formDefaultValue.value.quantity)
			formDefaultValue.value.quantity = form.quantity
			// console.log('ee axios', form.processing)
		} catch (error) {
			console.log("ERR1", errors);
			emits("onError", error?.response?.data)
			notify({
				title: trans("Something went wrong"),
				text: error?.response?.data?.message || error?.response?.data,
				type: "error",
			})
		} finally {
			form.processing = false
		}
	} else {
		form.transform((data) => ({
			[props.keySubmit || "quantity"]: data.quantity,
			...props.additionalData,
		})).submit(
			props.routeSubmit?.method || "post",
			route(props.routeSubmit?.name, props.routeSubmit?.parameters),
			{
				preserveScroll: true,
				onError: (errors) => {
					emits("onError", errors)
					notify({
						title: trans("Something went wrong"),
						text: errors?.message || 'Failed to process this action',
						type: "error",
					})
				},
				onSuccess: (response) => {
					emits("onSuccess", form.quantity, formDefaultValue.value.quantity)
				}
			},
		)
	}
}
const debounceSaveViaForm = debounce(onSaveViaForm, 1000)

const keyIconUndo = ref(0)

defineOptions({
	inheritAttrs: false,
})

const { stop, pause, resume } = watch(
	() => form.quantity,
	(newVal: number) => {
		emits("update:modelValue", newVal, onSaveViaForm)
		if (props.autoSave) {
			debounceSaveViaForm()
		}
	}
)

watch(
	() => props.modelValue,
	async (newVal: number) => {
		if (props.isWithRefreshModel) {
			const roundedVal = roundToDecimals(newVal)
			form.defaults('quantity', roundedVal)
			form.reset()
		}
	}
)

const onClickMinusButton = () => {
	// Check if the quantity is less than or equal to the minimum value and prevent decrease
	if (
		(props.bindToTarget?.min !== undefined && form.quantity <= props.bindToTarget?.min) ||
		(props.min !== undefined && form.quantity <= props.min)
	) {
		return false // Prevent decreasing when the quantity is at or below the min value
	} else {
		if (props.denominator) {
			form.quantity = roundToDecimals(Number(form.quantity) - Number((1 / props.denominator).toPrecision(5)))
		} else {
			form.quantity = roundToDecimals(form.quantity - 1)
		}
	}
}
const onClickPlusButton = () => {
	// Prevent increase when quantity is at or exceeds max value (including max being 0)
	if (
		(props.bindToTarget?.max !== undefined && form.quantity >= props.bindToTarget?.max) ||
		(props.max !== undefined && form.quantity >= props.max)
	) {
		return false // Prevent increase if quantity is at or exceeds max value
	} else {
		if (props.denominator) {
			form.quantity = roundToDecimals(Number(form.quantity) + Number((1 / props.denominator).toPrecision(5)))
		} else {
			form.quantity = roundToDecimals(form.quantity + 1)
		}
	}
}

const layout = inject("layout", {})

const holdInterval = ref<ReturnType<typeof setInterval> | null>(null)
const holdTimeout = ref<ReturnType<typeof setTimeout> | null>(null)

const startHold = (action: () => void) => {
	action()
	holdTimeout.value = setTimeout(() => {
		holdInterval.value = setInterval(action, 50)
	}, 400)
}

const stopHold = () => {
	if (holdInterval.value) {
		clearInterval(holdInterval.value)
		holdInterval.value = null
	}
	if (holdTimeout.value) {
		clearTimeout(holdTimeout.value)
		holdTimeout.value = null
	}
}

</script>

<template>
	<div class="relative w-fit" :class="parentClass">
		<div
			class="flex items-center justify-center border border-gray-300 rounded gap-y-1 px-1 py-0.5">
			<slot name="prefix"></slot>
			<!-- Button: Save -->
			<button
				v-if="!noUndoButton"
				@click.stop="() => (keyIconUndo++, form.reset('quantity'))"
				v-tooltip="trans('Reset value')"
				class="relative flex items-center justify-center px-2.5 lg:px-1 py-2.5 lg:py-1.5"
				:class="
					form.isDirty
						? 'cursor-pointer hover:text-gray-800 disabled:text-gray-400 hover:bg-gray-200 rounded'
						: 'text-gray-400'
				"
				:disabled="form.processing || !form.isDirty"
				type="submit">
				<div class="text-sm flex items-center">
					<Transition name="spin-to-left">
						<FontAwesomeIcon
							:key="keyIconUndo"
							icon="far fa-undo-alt"
							class=""
							fixed-width
							aria-hidden="true" />
					</Transition>
				</div>
			</button>

			<!-- Section: - and + -->
			<div
				class="w-fit transition-all relative inline-flex items-center justify-center"
				:class="bindToTarget?.fluid ? 'w-full' : 'w-28'">
				<!-- Button: Minus -->
				<div
					@mousedown.stop="() => props.readonly || form.processing ? null : startHold(onClickMinusButton)"
					@mouseup="stopHold"
					@mouseleave="stopHold"
					class="leading-4 inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 rounded px-2.5 lg:px-1 py-2.5 lg:py-1.5 text-xs justify-self-center"
					:class="[
						props.readonly || form.processing
							? 'text-gray-400 '
							:  (props.bindToTarget?.min !== undefined && form.quantity <= props.bindToTarget?.min) || (props.min !== undefined && form.quantity <= props.min)
								? 'text-gray-400'
								: 'cursor-pointer text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 '
					]"
				>
					<FontAwesomeIcon
						icon="fas fa-minus"
						:class="form.quantity < 1 ? 'text-gray-400' : ''"
						fixed-width
						aria-hidden="true" />
				</div>

				<!-- Input -->

				<div
					class="mx-1 text-center tabular-nums rounded"
					:style="{
						border: `1px dashed ${(colorTheme ? colorTheme : null) || '#374151'}55`,
					}">
					<span v-if="layout.app.environment == 'local' && props.denominator">
						Would only show in local <br>
						{{ form.quantity }}
						{{ roundToDecimals(props.denominator ? (roundToDecimals(Math.floor(form.quantity * props.denominator)) / props.denominator) : form.quantity) }}
					</span>
					<InputNumber
						vxmodel="form.quantity"
						:modelValue="props.denominator ? roundToDecimals(Math.floor(form.quantity * props.denominator)) : form.quantity"
						@update:model-value="(e) => (props.denominator? (form.quantity = roundToDecimals(e/props.denominator)) : (form.quantity = roundToDecimals(e)))"
						@input="(e) => (props.denominator ? (form.quantity = roundToDecimals(e.value/props.denominator)) : (form.quantity = roundToDecimals(e.value)))"
						buttonLayout="horizontal"
						:min="min || 0"
						:max="max || undefined"
						style="width: 100%"
						:disabled="props.readonly || form.processing || props.disableInput"
						inputClass="!p-1 lg:!p-0"
						:suffix="props.denominator ? '/' + props.denominator : undefined"
						:inputStyle="{
							width: bindToTarget?.fluid ? undefined : (
								props.denominator
									? '75px'
									: '50px'
							),
							color: props.readonly ? '#6b7280' : colorTheme ?? '#374151',
							border: 'none',
							textAlign: 'center',
							background: (colorTheme ? colorTheme + '22' : null) ?? 'transparent',
						}"
						v-bind="bindToTarget"
					/>
				</div>

				<!-- Button: Plus -->
				<div
					@mousedown.stop="() => props.readonly || form.processing ? null : startHold(onClickPlusButton)"
					@mouseup="stopHold"
					@mouseleave="stopHold"
					class="leading-4 inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 rounded px-2.5 lg:px-1 py-2.5 lg:py-1.5 text-xs justify-self-center"
					:class="[
						props.readonly || form.processing
							? 'text-gray-400 '
							: (props.bindToTarget?.max !== undefined && form.quantity >= props.bindToTarget?.max) || (props.max !== undefined && form.quantity >= props.max)
								? 'text-gray-400'
								: 'cursor-pointer text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 '
					]"
				>
					<FontAwesomeIcon
						icon="fas fa-plus"
						fixed-width
						aria-hidden="true"
					/>
				</div>
			</div>

			<!-- Button: Save -->
			<button
				v-if="!noSaveButton && !props.readonly"
				class="relative flex items-center justify-center px-1 py-0.5 text-sm"
				:class="{ 'text-gray-400': !form.isDirty }"
				:disabled="form.processing || !form.isDirty"
				type="submit">
				<slot
					name="save"
					:isProcessing="form.processing"
					:isDirty="form.isDirty"
					:quantity="form.quantity"
					:onSaveViaForm="onSaveViaForm">
					<LoadingIcon v-if="form.processing || props.isLoading" class="text-xl" />
					<template v-else>
						<FontAwesomeIcon
							v-if="form.isDirty"
							@click.stop="saveOnForm ? onSaveViaForm() : emits('onSave', form)"
							:style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
							icon="fad fa-save"
							fixed-width
							class="cursor-pointer text-xl"
							aria-hidden="true" />
						<FontAwesomeIcon
							v-else
							icon="fal fa-save"
							fixed-width
							class="text-xl"
							aria-hidden="true" />
					</template>
				</slot>
			</button>
			<slot></slot>
			<slot name="suffix"></slot>
		</div>

	</div>
</template>
