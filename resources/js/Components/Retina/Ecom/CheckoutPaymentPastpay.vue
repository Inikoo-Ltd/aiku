<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { inject, ref } from "vue"
import { faArrowRight, faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import CopyButton from "@/Components/Utils/CopyButton.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingText from "@/Components/Utils/LoadingText.vue"
import { notify } from "@kyvg/vue3-notification"

library.add(faArrowRight, faCheckCircle)

const props = defineProps<{
	data: {
		data: {
			bank_name: string
			bank_code: string
			iban: string
			account_number: string
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

const xxxx = {
	credentials: {
		api_key: "xxx",
	},
	charges: {
		options: [
			{ days: 30, charge: "2.20%" },
			{ days: 60, charge: "4.25%" },
		],
	},
}
const totalCost = 160

const selectedOption = ref<{ days: number; charge: string } | null>(null)

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
		<div class="mx-auto max-w-md">
			<img src="/storage/payment-providers/pastpay.png" alt="Pastpay" />
			<div class="mt-5 pt-4 border-t border-dashed">
				<div>
					{{ ctrans("Need to pay") }}:
					<span class="font-bold">{{
						locale.currencyFormat(currencyCode, Number(props.needToPay).toFixed(2))
					}}</span>
				</div>
				<div>{{ ctrans("Select your preferred method") }}:</div>
			</div>

			<!-- Section: method selector -->
			<div class="flex flex-col gap-y-2 mt-3">
				<button
					v-for="option in xxxx.charges.options"
					:key="option.days"
					type="button"
					@click="selectedOption = option"
					:class="[
						'flex flex-col gap-x-4 rounded-xl border p-6 ring-1 ring-inset text-left transition-colors',
						selectedOption?.days === option.days
							? 'border-blue-500 bg-blue-50 ring-blue-300'
							: 'border-gray-300 bg-gray-100 ring-white/10 hover:border-gray-400',
					]">
					<div class="flex flex-row items-center justify-between w-full">
						<div class="flex flex-col md:flex-row md:items-center gap-2">
							<h3 class="font-semibold">{{ option.days }} {{ ctrans("days") }}</h3>
							<div class="font-normal xtext-gray-400 text-sm">
								{{ option.charge }} {{ ctrans("charge") }}
							</div>
						</div>
						<FontAwesomeIcon
							v-if="selectedOption?.days === option.days"
							:icon="faCheckCircle"
							class="text-blue-500 text-xl"
							fixed-width
							aria-hidden="true" />
						<FontAwesomeIcon
							v-else
							icon="fal fa-circle"
							class="text-xl"
							fixed-width
							aria-hidden="true" />
					</div>
					<p class="text-gray-400 italic text-sm mt-1">
						{{ ctrans("Estimated cost") }} •
						{{
							locale.currencyFormat(
								currencyCode,
								(needToPay * (parseFloat(option.charge) / 100)).toFixed(2)
							)
						}}
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
