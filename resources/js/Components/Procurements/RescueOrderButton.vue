<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 2 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { computed, onBeforeUnmount, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLifeRing } from "@fal"
library.add(faLifeRing)

const props = withDefaults(
	defineProps<{
		orgPartnerId: number
		partnerName: string
		currencyCode: string
		draftReference?: string | null
		estimatedLines?: number | null
		estimatedCost?: number | null
		size?: string
	}>(),
	{ size: "s" }
)

const locale = useLocaleStore()

const steps = [
	"Finding what we are running out of",
	"Checking what :partner can spare without putting their own stock at risk",
	"Working out how much of each to order",
	"Adding the lines to the purchase order",
]

const isAsking = ref(false)
const isWorking = ref(false)
const budget = ref<number | null>(null)
const step = ref(0)
let stepTimer: ReturnType<typeof setInterval> | null = null

const stepText = computed(() => ctrans(steps[step.value], { partner: props.partnerName }))

const wholeMoney = (amount: number) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: props.currencyCode,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)

const currencySymbol = computed(() => wholeMoney(0).replace(/[\d\s.,]/g, ""))

const stopTimer = () => {
	if (stepTimer) {
		clearInterval(stepTimer)
		stepTimer = null
	}
}

const prepare = () => {
	isAsking.value = false
	router.post(
		route("grp.models.org-partner.rescue_purchase_order.store", {
			orgPartner: props.orgPartnerId,
		}),
		budget.value ? { budget: budget.value } : {},
		{
			onStart: () => {
				isWorking.value = true
				step.value = 0
				stepTimer = setInterval(() => {
					step.value = Math.min(step.value + 1, steps.length - 1)
				}, 2500)
			},
			onFinish: () => {
				stopTimer()
				isWorking.value = false
			},
			onError: (errors) => {
				notify({
					title: ctrans("No rescue order prepared"),
					text:
						errors.rescue ??
						errors.budget ??
						ctrans("Something went wrong, please try again"),
					type: "error",
				})
			},
		}
	)
}

onBeforeUnmount(stopTimer)
</script>

<template>
	<span class="inline-flex">
		<Button
			:label="
				draftReference
					? ctrans('Add rescue to :reference', { reference: draftReference })
					: ctrans('Prepare rescue order')
			"
			icon="fal fa-life-ring"
			:size="size"
			:loading="isWorking"
			@click="isAsking = true" />

		<Modal :isOpen="isAsking" width="w-full max-w-md" @onClose="isAsking = false">
			<form class="space-y-4 px-2 py-2 text-sm text-gray-700" @submit.prevent="prepare">
				<div>
					<div class="font-semibold text-gray-900">
						{{
							draftReference
								? ctrans("Add rescue to :reference", { reference: draftReference })
								: ctrans("Prepare rescue order")
						}}
					</div>
					<p class="mt-1 text-gray-500">
						{{
							ctrans(
								"Adds every SKO :partner can rescue that would lose sales, and every A/B bestseller, worst first, enough to get each out of the critical zone and never more than they can spare.",
								{ partner: partnerName }
							)
						}}
					</p>
				</div>

				<div
					v-if="estimatedLines"
					class="flex items-baseline justify-between rounded-md bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
					<span class="text-gray-500">{{ ctrans("Estimated order") }}</span>
					<span class="tabular-nums">
						{{ ctrans(":lines lines", { lines: locale.number(estimatedLines) }) }} ·
						<span class="font-semibold text-gray-900"
							>≈ {{ wholeMoney(estimatedCost ?? 0) }}</span
						>
					</span>
				</div>

				<label class="block">
					<span class="text-gray-600">{{ ctrans("Budget (optional)") }}</span>
					<div
						class="mt-1 flex items-center rounded-md ring-1 ring-gray-300 focus-within:ring-indigo-500">
						<span class="pl-3 text-gray-500">{{ currencySymbol }}</span>
						<input
							v-model.number="budget"
							type="number"
							min="1"
							step="any"
							inputmode="decimal"
							class="w-full border-0 bg-transparent py-1.5 pl-2 text-sm tabular-nums focus:ring-0"
							:placeholder="ctrans('No limit')" />
					</div>
					<span class="mt-1 block text-xs text-gray-500">
						{{
							ctrans(
								"For the whole order. A line that would go over it is skipped, cheaper ones after it still get in."
							)
						}}
					</span>
				</label>

				<div class="flex justify-end gap-2">
					<Button
						:label="ctrans('Cancel')"
						type="tertiary"
						size="s"
						@click="isAsking = false" />
					<Button
						:label="ctrans('Prepare')"
						icon="fal fa-life-ring"
						size="s"
						nativeType="submit" />
				</div>
			</form>
		</Modal>

		<Modal
			:isOpen="isWorking"
			width="w-full max-w-md"
			:isClosableInBackground="false"
			@onClose="() => {}">
			<div
				class="flex flex-col items-center gap-3 px-4 py-6 text-center text-sm text-gray-700"
				role="status"
				aria-live="polite">
				<LoadingIcon class="text-3xl text-indigo-500" />
				<div class="font-semibold text-gray-900">{{ ctrans("Crunching the numbers") }}</div>
				<div class="text-gray-500">{{ stepText }}…</div>
				<div class="text-xs text-gray-400">
					{{
						ctrans(
							"This can take a few seconds, the purchase order opens when it is ready"
						)
					}}
				</div>
			</div>
		</Modal>
	</span>
</template>
