<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { computed, inject, ref } from "vue"
import { faArrowRight, faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import CopyButton from "@/Components/Utils/CopyButton.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingText from "@/Components/Utils/LoadingText.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"

library.add(faArrowRight, faCheckCircle)

const props = defineProps<{
	data: {
		data: {
			bank_name: string
			bank_code: string
			iban: string
			account_number: string
            charges: any
		}
	}
	needToPay: number
	currency_code: string
	order: {

	}
}>()

const layout = inject("layout", retinaLayoutStructure)
const locale = inject("locale", aikuLocaleStructure)
const currencyCode = props.currency_code || layout.iris?.currency?.code


const selectedOption = ref<{ days: number; charge: string } | null>(null)

const chargeRate = (charge: string) => parseFloat(charge) / 100
const chargeAmountFor = (charge: string) => Number(props.needToPay) * chargeRate(charge)

const feeAmount = computed(() =>
	selectedOption.value ? chargeAmountFor(selectedOption.value.charge) : 0
)
const totalAmount = computed(() => Number(props.needToPay) + feeAmount.value)

const isLoading = ref(false)
const onSubmitPlaceOrder = async () => {
	try {
		isLoading.value = true
		const response = await axios.post(
			route("retina.models.place_order_pay_by_pastpay", {
				order: props.order.slug
			}),
			{
				days: selectedOption.value?.days,
			}
		)
		isModalPastpayRedirected.value = true
		setTimeout(() => {
			window.location.href = response.data.data
		}, 800);
	} catch (error: any) {

		const errorMessage = error.response?.data?.message
			|| error.response?.data?.errors
			|| error.message
			|| trans("Please try again or contact administrator")
		notify({
			title: trans("Something went wrong"),
			text: errorMessage,
			type: 'error'
		})
	} finally {
		isLoading.value = false
	}

	// router.post(
	// 	route("retina.models.place_order_pay_by_pastpay", {
	// 		order: props.order.slug
	// 	}),
	// 	{
	// 		days: selectedOption.value?.days,
	// 	},
	// 	{
	// 		onStart: () => {
	// 			isLoading.value = true
	// 		},
	// 		onFinish: () => {
	// 			isLoading.value = false
	// 		},
	// 		onSuccess: (response) => {
	// 			window.location.href = response
	// 		},
	// 	}
	// )
}

const isModalPastpayRedirected = ref(false)
</script>

<template>
	<div class="relative w-full max-w-xl mx-auto my-4 md:my-8 overflow-hidden">
		<div class="mx-auto max-w-lg">
			<img src="/storage/payment-providers/pastpay.png" alt="Pastpay" />
			<div class="mt-5 pt-5 border-t border-dashed border-gray-300">
				<div class="overflow-hidden rounded-xl ring-1 ring-gray-200 divide-y divide-gray-200">
					<div class="flex items-center justify-between px-4 py-3">
						<span class="text-sm text-gray-600">{{ ctrans("Need to pay") }}</span>
						<span class="font-semibold text-gray-900 tabular-nums">
							{{ locale.currencyFormat(currencyCode, Number(props.needToPay)) }}
						</span>
					</div>

					<Transition name="slide-down">
						<div v-if="selectedOption" class="flex items-center justify-between px-4 py-3">
							<span class="text-sm text-gray-600">
								{{ ctrans("Pastpay fee") }}
								<span class="text-gray-400">({{ selectedOption.charge }}%)</span>
							</span>
							<span class="font-semibold text-blue-600 tabular-nums whitespace-nowrap">
								+ {{ locale.currencyFormat(currencyCode, feeAmount) }}
							</span>
						</div>
					</Transition>

					<div class="flex items-center justify-between bg-gray-50 px-4 py-3">
						<span class="font-medium text-gray-900">{{ ctrans("Total") }}</span>
						<span class="text-lg font-bold text-gray-900 tabular-nums">
							{{ locale.currencyFormat(currencyCode, totalAmount) }}
						</span>
					</div>
				</div>

				<div class="mt-5 text-sm font-medium text-gray-700">
					{{ ctrans("Select your preferred method") }}
				</div>
			</div>

			<!-- Section: method selector -->
			<div class="mt-3 flex flex-col gap-y-3">
				<button
					v-for="option in props.data.data.charges"
					:key="option.days"
					type="button"
					@click="selectedOption = option"
					:class="[
						'group flex w-full flex-col gap-y-2 rounded-xl border p-5 text-left ring-1 ring-inset transition-all',
						selectedOption?.days === option.days
							? 'border-blue-500 bg-blue-50 ring-blue-200'
							: 'border-gray-200 bg-white ring-transparent hover:border-gray-300 hover:bg-gray-50',
					]">
					<div class="flex w-full items-center justify-between gap-4">
						<div class="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
							<h3 class="font-semibold text-gray-900">{{ option.days }} {{ ctrans("days") }}</h3>
							<span
								class="inline-flex w-fit items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
								{{ option.charge }}% {{ ctrans("charge") }}
							</span>
						</div>
						<FontAwesomeIcon
							:icon="selectedOption?.days === option.days ? faCheckCircle : 'fal fa-circle'"
							:class="selectedOption?.days === option.days ? 'text-blue-500' : 'text-gray-300'"
							class="text-xl"
							fixed-width
							aria-hidden="true" />
					</div>
					<p class="text-sm text-gray-500">
						{{ ctrans("Estimated cost") }} •
						<span class="font-medium tabular-nums text-gray-700">
							{{ locale.currencyFormat(currencyCode, chargeAmountFor(option.charge)) }}
						</span>
					</p>
				</button>
			</div>

			<Button
				full
				:label="ctrans('Place order')"
				class="mt-6"
				:disabled="!selectedOption"
				@click="() => onSubmitPlaceOrder()"
				:loading="isLoading"
				iconRight="fas fa-arrow-right" />
		</div>

		<Modal
			:isOpen="isModalPastpayRedirected"
			aonClose="isModalPastpayRedirected = false"
			width="w-full max-w-lg">
			<div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
				<div class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full">
					<div>
						<!-- <div
							class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
							<FontAwesomeIcon
								:icon="faSmile"
								class="text-green-500 text-2xl"
								fixed-width
								aria-hidden="true" />
						</div> -->

						<div class="mt-3 text-center sm:mt-5">
							<div as="h3" class="font-semibold text-2xl">
								{{ ctrans("Pastpay payment selected.") }}
							</div>
							<div class="mt-2 text-sm text-gray-500">
								{{ ctrans( "You will be redirected to Pastpay to complete your payment." ) }}
							</div>
						</div>
					</div>

					<div class="mt-5 sm:mt-6 flex flex-col gap-4 text-center items-center">
						<LoadingText />
					</div>
				</div>
			</div>
		</Modal>
	</div>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
	transition: opacity 0.25s ease, transform 0.25s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
	opacity: 0;
	transform: translateY(-0.5rem);
}
</style>
