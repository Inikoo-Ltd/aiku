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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLifeRing, faCheckSquare, faSquare, faQuestionCircle, faClipboardList } from "@fal"
library.add(faLifeRing, faCheckSquare, faSquare, faQuestionCircle, faClipboardList)

interface RescueBucket {
	bucket: "out" | "w1" | "w2" | "w3"
	label: string
	count: number
	cost: number
	order_lines: number
	order_cost: number
	left_out: Record<string, number>
}

const props = withDefaults(
	defineProps<{
		orgPartnerId: number
		partnerName: string
		currencyCode: string
		draftReference?: string | null
		buckets: RescueBucket[]
		size?: string
		isHub?: boolean
	}>(),
	{ size: "s", isHub: false }
)

const locale = useLocaleStore()

const steps = [
	"Finding what we are running out of",
	"Checking what :partner can spare without putting their own stock at risk",
	"Working out how much of each to order",
	"Adding the lines to the purchase order",
]
if (props.isHub) {
	steps[1] = "Checking what :partner sells"
	steps[3] = "Adding the lines to the shopping list"
}

const buttonLabel = computed(() => {
	if (props.isHub) {
		return ctrans("Prepare order")
	}
	return props.draftReference
		? ctrans("Add rescue to :reference", { reference: props.draftReference })
		: ctrans("Prepare rescue order")
})

const isAsking = ref(false)
const isWorking = ref(false)
const budget = ref<number | null>(null)
const selectedBuckets = ref<string[]>(["out", "w1", "w2"])
const worstOnly = ref(true)

const bucketShortLabel: Record<string, string> = {
	out: ctrans("Out of stock"),
	w1: ctrans("Doomed"),
	w2: ctrans("Critical"),
	w3: ctrans("Danger"),
}

const bucketDot: Record<string, string> = {
	out: "bg-red-700",
	w1: "bg-red-500",
	w2: "bg-orange-500",
	w3: "bg-amber-400",
}

const availableBuckets = computed(() => props.buckets.filter((bucket) => bucket.count > 0))

const bucketLines = (bucket: RescueBucket) => (worstOnly.value ? bucket.order_lines : bucket.count)
const bucketCost = (bucket: RescueBucket) => (worstOnly.value ? bucket.order_cost : bucket.cost)

const estimate = computed(() =>
	availableBuckets.value
		.filter((bucket) => selectedBuckets.value.includes(bucket.bucket))
		.reduce(
			(total, bucket) => ({
				lines: total.lines + bucketLines(bucket),
				cost: total.cost + bucketCost(bucket),
			}),
			{ lines: 0, cost: 0 }
		)
)
const isCappedByBudget = computed(() => !!budget.value && budget.value < estimate.value.cost)

const skippedLines = computed(() =>
	availableBuckets.value
		.filter((bucket) => selectedBuckets.value.includes(bucket.bucket))
		.reduce((total, bucket) => total + bucket.count - bucket.order_lines, 0)
)

const showWhy = ref(false)

const leftOutLabels = computed<Record<string, string>>(() => ({
	not_selling: ctrans("Not selling any more"),
	not_stocked: ctrans(":partner does not stock them", { partner: props.partnerName }),
	on_draft: props.isHub
		? ctrans("Already on the shopping list")
		: props.draftReference
			? ctrans("Already on :reference", { reference: props.draftReference })
			: ctrans("Already on the order being prepared"),
	coming: ctrans("Already ordered from someone, on the way or on the shopping list"),
	partner_no_forecast: ctrans(
		"Too little sales history at :partner to know what they can spare",
		{ partner: props.partnerName }
	),
	partner_short: ctrans(":partner needs them for their own customers", {
		partner: props.partnerName,
	}),
	no_price: ctrans(":partner has no price for them", { partner: props.partnerName }),
	too_small: ctrans("Too small an order to be worth picking"),
	filter: ctrans("Would not run out, left out by the option above"),
}))

const whyRows = computed(() => {
	const totals: Record<string, number> = {}
	props.buckets
		.filter((bucket) => bucket.count === 0 || selectedBuckets.value.includes(bucket.bucket))
		.forEach((bucket) => {
			Object.entries(bucket.left_out ?? {}).forEach(([reason, count]) => {
				totals[reason] = (totals[reason] ?? 0) + count
			})
		})
	if (worstOnly.value && skippedLines.value) {
		totals.filter = skippedLines.value
	}

	return Object.entries(totals)
		.filter(([, count]) => count > 0)
		.sort(([, a], [, b]) => b - a)
		.map(([reason, count]) => ({ reason, label: leftOutLabels.value[reason] ?? reason, count }))
})

const whyTotal = computed(() =>
	whyRows.value.reduce((total, row) => total + row.count, estimate.value.lines)
)

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
		route(
			props.isHub
				? "grp.models.org-partner.shopping_list_order.store"
				: "grp.models.org-partner.rescue_purchase_order.store",
			{ orgPartner: props.orgPartnerId }
		),
		{
			buckets: selectedBuckets.value,
			worst_only: worstOnly.value,
			...(budget.value ? { budget: budget.value } : {}),
		},
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
					title: props.isHub
						? ctrans("No order prepared")
						: ctrans("No rescue order prepared"),
					text:
						errors.rescue ??
						errors.budget ??
						errors.buckets ??
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
			:label="buttonLabel"
			:icon="isHub ? 'fal fa-clipboard-list' : 'fal fa-life-ring'"
			:size="size"
			:loading="isWorking"
			@click="isAsking = true" />

		<Modal :isOpen="isAsking" width="w-full max-w-md md:max-w-2xl" @onClose="isAsking = false">
			<form class="space-y-4 px-2 py-2 text-sm text-gray-700" @submit.prevent="prepare">
				<div>
					<div class="font-semibold text-gray-900">
						{{ buttonLabel }}
					</div>
					<p class="mt-1 text-gray-500">
						{{
							isHub
								? ctrans(
										"Worst first, enough of each to get out of the critical zone. :partner makes them, so their stock is no limit.",
										{ partner: partnerName }
									)
								: ctrans(
										"Worst first, enough of each to get out of the critical zone, never more than :partner can spare.",
										{ partner: partnerName }
									)
						}}
					</p>
				</div>

				<fieldset class="space-y-1.5">
					<legend
						class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-500">
						{{ ctrans("What to add") }}
					</legend>
					<label
						v-for="bucket in availableBuckets"
						:key="bucket.bucket"
						v-tooltip="bucket.label"
						class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition-colors hover:bg-gray-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gray-300"
						:class="selectedBuckets.includes(bucket.bucket) ? '' : 'opacity-60'">
						<input
							v-model="selectedBuckets"
							type="checkbox"
							:value="bucket.bucket"
							class="sr-only" />
						<FontAwesomeIcon
							:icon="
								selectedBuckets.includes(bucket.bucket)
									? 'fal fa-check-square'
									: 'fal fa-square'
							"
							class="text-base text-gray-700"
							fixed-width
							aria-hidden="true" />
						<span
							class="h-2.5 w-2.5 shrink-0 rounded-full"
							:class="bucketDot[bucket.bucket]"
							aria-hidden="true" />
						<span class="font-medium text-gray-800">{{
							bucketShortLabel[bucket.bucket]
						}}</span>
						<span class="ml-auto w-16 text-right text-xs tabular-nums text-gray-500">
							{{
								ctrans(":lines lines", {
									lines: locale.number(bucketLines(bucket)),
								})
							}}
						</span>
						<span class="w-20 text-right tabular-nums font-medium text-gray-900">{{
							wholeMoney(bucketCost(bucket))
						}}</span>
					</label>
				</fieldset>

				<label
					class="flex cursor-pointer items-start gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gray-300">
					<input v-model="worstOnly" type="checkbox" class="sr-only" />
					<FontAwesomeIcon
						:icon="worstOnly ? 'fal fa-check-square' : 'fal fa-square'"
						class="mt-0.5 text-base text-gray-700"
						fixed-width
						aria-hidden="true" />
					<span>
						<span class="block font-medium text-gray-800">{{
							ctrans("Only items that would run out")
						}}</span>
						<span class="block text-xs text-gray-500">
							{{
								ctrans(
									"Leaves out items with enough stock to last until this order arrives and a month after. Items already out of stock and your best sellers (rank A and B) always go in."
								)
							}}
							<template v-if="worstOnly && skippedLines">{{
								ctrans(":lines lines left out.", {
									lines: locale.number(skippedLines),
								})
							}}</template>
						</span>
					</span>
				</label>

				<div
					class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5">
					<span class="flex items-center gap-1.5 font-medium text-gray-700">
						{{ ctrans("Estimated order") }}
						<button
							type="button"
							v-tooltip="ctrans('Why this many lines?')"
							:aria-label="ctrans('Why this many lines?')"
							:aria-expanded="showWhy"
							class="text-gray-400 hover:text-gray-700"
							@click="showWhy = !showWhy">
							<FontAwesomeIcon
								icon="fal fa-question-circle"
								fixed-width
								aria-hidden="true" />
						</button>
					</span>
					<span class="flex items-baseline gap-2 tabular-nums">
						<span class="text-xs text-gray-500">{{
							ctrans(isCappedByBudget ? "up to :lines lines" : ":lines lines", {
								lines: locale.number(estimate.lines),
							})
						}}</span>
						<span class="text-base font-semibold text-gray-900"
							>≈ {{ wholeMoney(isCappedByBudget ? budget : estimate.cost) }}</span
						>
					</span>
				</div>

				<div v-if="showWhy" class="rounded-lg border border-gray-200 px-3 py-2.5 text-xs">
					<p class="mb-2 text-gray-500">
						{{
							ctrans(
								"Of our :total items out of stock, doomed or critical, these are why most are not in this order:",
								{ total: locale.number(whyTotal) }
							)
						}}
					</p>
					<table class="w-full">
						<tbody>
							<tr
								v-for="row in whyRows"
								:key="row.reason"
								class="border-b border-gray-100">
								<td class="py-1 pr-2 text-gray-600">{{ row.label }}</td>
								<td class="py-1 text-right tabular-nums text-gray-900">
									{{ locale.number(row.count) }}
								</td>
							</tr>
							<tr>
								<td class="pt-1.5 pr-2 font-semibold text-gray-900">
									{{ ctrans("In this order") }}
								</td>
								<td
									class="pt-1.5 text-right font-semibold tabular-nums text-gray-900">
									{{ locale.number(estimate.lines) }}
								</td>
							</tr>
						</tbody>
					</table>
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
							isHub
								? ctrans(
										"For the whole shopping list. A line that would go over it is skipped, cheaper ones after it still get in."
									)
								: ctrans(
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
						:disabled="!estimate.lines"
						:icon="isHub ? 'fal fa-clipboard-list' : 'fal fa-life-ring'"
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
						isHub
							? ctrans(
									"This can take a few seconds, the shopping list opens when it is ready"
								)
							: ctrans(
									"This can take a few seconds, the purchase order opens when it is ready"
								)
					}}
				</div>
			</div>
		</Modal>
	</span>
</template>
