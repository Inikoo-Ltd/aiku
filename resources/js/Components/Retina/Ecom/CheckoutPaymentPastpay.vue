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
const onSubmitPlaceOrder = () => {
	router.post(
		route("retina.models.place_order_pay_by_pastpay"),
		{
			charges: selectedOption.value,
		},
		{
			onStart: () => {
				isLoading.value = true
			},
			onFinish: () => {
				isLoading.value = false
			},
			onSuccess: (response) => {
				window.location.href = response
			},
		}
	)
}
</script>

<template>
	<div class="relative w-full max-w-xl mx-auto my-4 md:my-8 overflow-hidden">
		<div class="mx-auto max-w-md">
			<img src="/storage/payment-providers/pastpay.png" alt="Pastpay" />
			<div class="mt-5 pt-4 border-t border-dashed">
				<div>
					{{ trans("Need to pay") }}:
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
	</div>
</template>
