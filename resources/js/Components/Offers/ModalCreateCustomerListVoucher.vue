<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { ref, computed, watch } from "vue"
import { Checkbox, DatePicker, InputNumber, RadioButton } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faTimesCircle } from "@fas"
import { faTrashAlt, faPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import PureInput from "@/Components/Pure/PureInput.vue"
import Toggle from "@/Components/Pure/Toggle.vue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"

library.add(faCheckCircle, faTimesCircle, faTrashAlt, faPlus)

const props = defineProps<{
	shop_data: {
		id: number
		slug: string
		organisation: string
		offercampaign: string
		currency_code: string
		default_dates: {
			start: string
			end: string
		}
	}
}>()

type CustomerState = "losing" | "lost"
type CodeType = "shared" | "unique"
type RewardType = "percentage_off" | "amount_off" | "discounted_shipping" | "gifts"
type Gift = { product_id: number | null; quantity: number | null }
type PreviewCustomer = { id: number; reference: string; name: string; state: string; last_invoiced_at: string | null }

const MAX_AMOUNT_OFF_RATIO = 0.3

const stateOptions: { value: CustomerState; label: string }[] = [
	{ value: "losing", label: "Potential Comebacks" },
	{ value: "lost", label: "Dormant" },
]

const rewardOptions: { value: RewardType; label: string }[] = [
	{ value: "percentage_off", label: "Percentage off" },
	{ value: "amount_off", label: "Amount off" },
	{ value: "discounted_shipping", label: "Free shipping" },
	{ value: "gifts", label: "Free gifts" },
]

const isOpenModal = ref(false)
const isLoadingSubmit = ref(false)

const states = ref<CustomerState[]>([])
const orderedOnce = ref(false)
const orderedOnceFromMonths = ref<number | null>(3)
const orderedOnceToMonths = ref<number | null>(6)

const name = ref("")
const codeType = ref<CodeType>("unique")
const voucherCode = ref("")
const codePrefix = ref("")
const minimumAmount = ref<number | null>(0)
const startDate = ref<Date | null>(null)
const endDate = ref<Date | null>(null)
const rewardType = ref<RewardType>("percentage_off")
const percentageOff = ref<number | null>(null)
const amountOff = ref<number | null>(null)
const gifts = ref<Gift[]>([{ product_id: null, quantity: 1 }])
const showOnCustomerDashboard = ref(true)

const productFetchRoute = {
	name: "grp.json.shop.products",
	parameters: { shop: props.shop_data.slug },
}

const today = new Date(new Date().setHours(0, 0, 0, 0))

const maxAmountOff = computed(() => Math.round((minimumAmount.value ?? 0) * MAX_AMOUNT_OFF_RATIO * 100) / 100)

const segmentsPayload = computed(() => ({
	states: states.value,
	ordered_once: orderedOnce.value ? 1 : 0,
	ordered_once_from_months: orderedOnce.value ? orderedOnceFromMonths.value : null,
	ordered_once_to_months: orderedOnce.value ? orderedOnceToMonths.value : null,
}))

const hasSegments = computed(() => states.value.length > 0 || orderedOnce.value)

const isOrderedOnceRangeValid = computed(() => {
	if (!orderedOnce.value) return true
	return orderedOnceFromMonths.value !== null && orderedOnceToMonths.value !== null
})

const preview = ref<{ number_customers: number; number_email_subscribers: number; customers: PreviewCustomer[] } | null>(null)
const isLoadingPreview = ref(false)
const hasPreviewError = ref(false)
let previewTimeout: ReturnType<typeof setTimeout> | null = null

const fetchPreview = async () => {
	isLoadingPreview.value = true
	hasPreviewError.value = false
	try {
		const { data } = await axios.get(
			route("grp.json.shop.voucher_customer_list_preview", { shop: props.shop_data.id }),
			{ params: segmentsPayload.value }
		)
		preview.value = data
	} catch (error) {
		preview.value = null
		hasPreviewError.value = true
	} finally {
		isLoadingPreview.value = false
	}
}

watch(segmentsPayload, () => {
	if (previewTimeout) clearTimeout(previewTimeout)
	preview.value = null
	hasPreviewError.value = false

	if (!hasSegments.value || !isOrderedOnceRangeValid.value) {
		isLoadingPreview.value = false
		return
	}

	isLoadingPreview.value = true
	previewTimeout = setTimeout(fetchPreview, 400)
}, { deep: true })

const isCheckingCode = ref(false)
const codeExists = ref<boolean | null>(null)
let codeCheckTimeout: ReturnType<typeof setTimeout> | null = null

const codeToCheck = computed(() => (codeType.value === "shared" ? voucherCode.value : codePrefix.value).trim())
const codeHasInvalidCharacters = computed(() => {
	if (!codeToCheck.value) return false
	return codeType.value === "shared" ? !/^[A-Za-z0-9_-]+$/.test(codeToCheck.value) : !/^[A-Za-z0-9]+$/.test(codeToCheck.value)
})

const checkCode = async (code: string) => {
	isCheckingCode.value = true
	try {
		const { data } = await axios.get(
			route("grp.org.shops.show.discounts.campaigns.check_voucher", {
				organisation: props.shop_data.organisation,
				shop: props.shop_data.slug,
				offerCampaign: props.shop_data.offercampaign,
			}),
			{ params: { code } }
		)
		codeExists.value = data.exists
	} catch (error) {
		codeExists.value = null
	} finally {
		isCheckingCode.value = false
	}
}

watch(codeToCheck, (code) => {
	codeExists.value = null
	if (codeCheckTimeout) clearTimeout(codeCheckTimeout)

	if (!code || codeHasInvalidCharacters.value) {
		isCheckingCode.value = false
		return
	}

	codeCheckTimeout = setTimeout(() => checkCode(code), 400)
})

const exampleUniqueCode = computed(() => `${(codePrefix.value || "PREFIX").toUpperCase()}-7KQ2MX`)

const addGift = () => {
	gifts.value.push({ product_id: null, quantity: 1 })
}

const removeGift = (index: number) => {
	gifts.value.splice(index, 1)
}

const isRewardInvalid = computed(() => {
	if (rewardType.value === "percentage_off") {
		return !percentageOff.value || percentageOff.value <= 0 || percentageOff.value >= 100
	}
	if (rewardType.value === "amount_off") {
		if (!minimumAmount.value || minimumAmount.value <= 0) return true
		return !amountOff.value || amountOff.value <= 0 || amountOff.value > maxAmountOff.value
	}
	if (rewardType.value === "gifts") {
		return gifts.value.length === 0 || gifts.value.some((gift) => !gift.product_id || !gift.quantity)
	}
	return false
})

const isFormInvalid = computed(() => {
	if (!hasSegments.value || !isOrderedOnceRangeValid.value) return true
	if (!preview.value?.number_customers) return true
	if (!name.value.trim()) return true
	if (!codeToCheck.value || codeHasInvalidCharacters.value || codeExists.value !== false) return true
	if (minimumAmount.value === null || minimumAmount.value < 0) return true
	if (!startDate.value || !endDate.value) return true
	return isRewardInvalid.value
})

function formatDate(date: Date | null) {
	if (!date) return null
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, "0")
	const day = String(date.getDate()).padStart(2, "0")
	return `${year}-${month}-${day}`
}

function resetForm() {
	states.value = []
	orderedOnce.value = false
	orderedOnceFromMonths.value = 3
	orderedOnceToMonths.value = 6
	preview.value = null
	hasPreviewError.value = false
	name.value = ""
	codeType.value = "unique"
	voucherCode.value = ""
	codePrefix.value = ""
	codeExists.value = null
	minimumAmount.value = 0
	startDate.value = new Date(props.shop_data.default_dates.start)
	endDate.value = new Date(props.shop_data.default_dates.end)
	rewardType.value = "percentage_off"
	percentageOff.value = null
	amountOff.value = null
	gifts.value = [{ product_id: null, quantity: 1 }]
	showOnCustomerDashboard.value = true
}

const openModal = () => {
	resetForm()
	isOpenModal.value = true
}

const closeModal = () => {
	isOpenModal.value = false
}

const submit = () => {
	isLoadingSubmit.value = true

	axios
		.post(route("grp.models.store_customer_list_voucher", { shop: props.shop_data.id }), {
			...segmentsPayload.value,
			name: name.value,
			code_type: codeType.value,
			voucher: codeType.value === "shared" ? voucherCode.value : null,
			code_prefix: codeType.value === "unique" ? codePrefix.value : null,
			offer_amount: minimumAmount.value,
			start_at: formatDate(startDate.value),
			end_at: formatDate(endDate.value),
			show_on_customer_dashboard: showOnCustomerDashboard.value,
			allowance_type: rewardType.value,
			percentage_off: rewardType.value === "percentage_off" ? percentageOff.value : null,
			amount_off: rewardType.value === "amount_off" ? amountOff.value : null,
			gifts: rewardType.value === "gifts" ? gifts.value : null,
		})
		.then((response) => {
			notify({
				title: ctrans("Voucher created"),
				text: ctrans("Use Email customers on the voucher page to send it."),
				type: "success",
			})
			closeModal()
			router.visit(
				route("grp.org.shops.show.discounts.campaigns.offer.show", {
					organisation: props.shop_data.organisation,
					shop: props.shop_data.slug,
					offerCampaign: props.shop_data.offercampaign,
					offer: response.data.slug,
				})
			)
		})
		.catch((error) => {
			const errors = error.response?.data?.errors || {}
			notify({
				title: ctrans("Voucher not created"),
				text: Object.values(errors).flat().join(". ") || ctrans("Please try again."),
				type: "error",
			})
		})
		.finally(() => {
			isLoadingSubmit.value = false
		})
}
</script>

<template>
	<div>
		<Button :label="ctrans('Create customer voucher')" icon="fal fa-users" @click="openModal" />

		<Modal :isOpen="isOpenModal" width="w-full max-w-3xl" @close="closeModal">
			<div class="p-1 space-y-6">
				<h2 class="text-2xl font-bold text-center">{{ ctrans("Create customer voucher") }}</h2>

				<section class="space-y-3">
					<h3 class="font-semibold">{{ ctrans("Who gets it") }}</h3>
					<p class="text-sm text-gray-500">{{ ctrans("Customers in any of the ticked groups are added to the list.") }}</p>

					<div class="flex flex-wrap gap-2">
						<label
							v-for="option in stateOptions"
							:key="option.value"
							:for="`voucher-state-${option.value}`"
							class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer text-sm"
							:class="states.includes(option.value) ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
							<Checkbox v-model="states" :value="option.value" :inputId="`voucher-state-${option.value}`" />
							<span>{{ ctrans(option.label) }}</span>
						</label>

						<label
							for="voucher-ordered-once"
							class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer text-sm"
							:class="orderedOnce ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
							<Checkbox v-model="orderedOnce" binary inputId="voucher-ordered-once" />
							<span>{{ ctrans("Ordered only once") }}</span>
						</label>
					</div>

					<div v-if="orderedOnce" class="flex flex-wrap items-center gap-2 text-sm">
						<span>{{ ctrans("Their only order was between") }}</span>
						<InputNumber v-model="orderedOnceFromMonths" :min="0" :max="120" inputClass="w-16" />
						<span>{{ ctrans("and") }}</span>
						<InputNumber v-model="orderedOnceToMonths" :min="0" :max="120" inputClass="w-16" />
						<span>{{ ctrans("months ago") }}</span>
					</div>

					<div v-if="hasSegments && (isLoadingPreview || preview || hasPreviewError)" class="rounded-lg border border-gray-200 p-3 text-sm">
						<div v-if="isLoadingPreview" class="flex items-center gap-x-2 text-gray-500">
							<LoadingIcon />
							{{ ctrans("Counting customers") }}…
						</div>
						<p v-else-if="hasPreviewError" class="text-red-500">
							{{ ctrans("Could not count the customers. Change a group to try again.") }}
						</p>
						<template v-else-if="preview">
							<p v-if="preview.number_customers === 0" class="text-amber-700">
								{{ ctrans("No customers match these groups.") }}
							</p>
							<template v-else>
								<p>
									<span class="font-semibold">{{ preview.number_customers }}</span>
									{{ ctrans("customers will get the voucher.") }}
									<span class="text-gray-500">
										{{ ctrans(":count of them can receive marketing emails.", { count: preview.number_email_subscribers }) }}
									</span>
								</p>
								<ul class="mt-2 divide-y divide-gray-100">
									<li v-for="customer in preview.customers" :key="customer.id" class="flex justify-between py-1">
										<span>{{ customer.name }} <span class="text-gray-400">{{ customer.reference }}</span></span>
										<span class="text-gray-500">{{ customer.state }}</span>
									</li>
								</ul>
								<p v-if="preview.number_customers > preview.customers.length" class="mt-1 text-xs text-gray-400">
									{{ ctrans("And :count more. The full list is on the voucher page once it is created.", { count: preview.number_customers - preview.customers.length }) }}
								</p>
							</template>
						</template>
					</div>
				</section>

				<section class="space-y-4">
					<h3 class="font-semibold">{{ ctrans("Voucher") }}</h3>

					<div class="space-y-2">
						<label class="font-medium flex items-center gap-x-1">
							{{ ctrans("Name") }}
							<InformationIcon :information="ctrans('Customers see this name on their dashboard and in the basket.')" />
						</label>
						<PureInput v-model="name" :placeholder="ctrans('e.g. 15% off your next order')" />
					</div>

					<div class="space-y-2">
						<label class="font-medium">{{ ctrans("Code") }}</label>
						<div class="flex flex-wrap gap-2">
							<label
								for="voucher-code-unique"
								class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer text-sm"
								:class="codeType === 'unique' ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
								<RadioButton v-model="codeType" value="unique" inputId="voucher-code-unique" />
								<span>{{ ctrans("A different code for each customer") }}</span>
							</label>
							<label
								for="voucher-code-shared"
								class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer text-sm"
								:class="codeType === 'shared' ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
								<RadioButton v-model="codeType" value="shared" inputId="voucher-code-shared" />
								<span>{{ ctrans("One code for everyone") }}</span>
							</label>
						</div>

						<template v-if="codeType === 'unique'">
							<PureInput v-model="codePrefix" :maxLength="10" :placeholder="ctrans('Code prefix, e.g. COMEBACK')" />
							<p class="text-xs text-gray-500">{{ ctrans("Codes will look like :example", { example: exampleUniqueCode }) }}</p>
						</template>
						<PureInput v-else v-model="voucherCode" :maxLength="16" :placeholder="ctrans('Voucher code, e.g. COMEBACK15')" />

						<p v-if="codeHasInvalidCharacters" class="text-sm text-red-500">
							{{ codeType === "unique" ? ctrans("Use letters and numbers only") : ctrans("Use letters, numbers, - and _ only") }}
						</p>
						<p v-else-if="isCheckingCode" class="text-sm text-gray-500 flex items-center gap-x-1">
							<LoadingIcon class="text-xs" />
							{{ ctrans("Checking code") }}…
						</p>
						<p v-else-if="codeExists === true" class="text-sm text-red-500 flex items-center gap-x-1">
							<FontAwesomeIcon icon="fas fa-times-circle" class="text-xs" fixed-width />
							{{ ctrans("This code is already in use") }}
						</p>
						<p v-else-if="codeExists === false" class="text-sm text-green-600 flex items-center gap-x-1">
							<FontAwesomeIcon icon="fas fa-check-circle" class="text-xs" fixed-width />
							{{ ctrans("Code is available") }}
						</p>
					</div>

					<div class="space-y-2">
						<label class="font-medium">{{ ctrans("Minimum order amount") }}</label>
						<InputNumber
							v-model="minimumAmount"
							class="w-full"
							mode="currency"
							:currency="shop_data.currency_code"
							locale="en-US"
							:min="0" />
					</div>

					<div class="grid grid-cols-2 gap-x-6">
						<div>
							<label class="font-medium flex items-center gap-x-1">
								{{ ctrans("Valid from") }}
							</label>
							<DatePicker v-model="startDate" :minDate="today" showIcon />
						</div>
						<div>
							<label class="font-medium flex items-center gap-x-1">
								{{ ctrans("Valid until") }}
								<InformationIcon :information="ctrans('The voucher stays on the customer dashboard until this date.')" />
							</label>
							<DatePicker v-model="endDate" :minDate="startDate ?? today" showIcon />
						</div>
					</div>

					<div class="space-y-3">
						<label class="font-medium">{{ ctrans("Reward") }}</label>
						<div class="flex flex-wrap gap-2">
							<label
								v-for="option in rewardOptions"
								:key="option.value"
								:for="`voucher-reward-${option.value}`"
								class="flex items-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer text-sm"
								:class="rewardType === option.value ? 'border-green-500 bg-green-50 text-green-700 font-semibold' : 'border-gray-200 hover:border-gray-300'">
								<RadioButton v-model="rewardType" :value="option.value" :inputId="`voucher-reward-${option.value}`" />
								<span>{{ ctrans(option.label) }}</span>
							</label>
						</div>

						<InputNumber
							v-if="rewardType === 'percentage_off'"
							v-model="percentageOff"
							class="w-full"
							suffix="%"
							:min="0"
							:max="99"
							:placeholder="ctrans('Discount, 1 to 99')" />

						<div v-if="rewardType === 'amount_off'" class="space-y-1">
							<InputNumber
								v-model="amountOff"
								class="w-full"
								mode="currency"
								:currency="shop_data.currency_code"
								locale="en-US"
								:min="0"
								:disabled="!minimumAmount || minimumAmount <= 0"
								:placeholder="ctrans('Amount off')" />
							<p v-if="!minimumAmount || minimumAmount <= 0" class="text-xs text-amber-600">
								{{ ctrans("Set a minimum order amount first: amount off vouchers require one.") }}
							</p>
							<p v-else class="text-xs text-gray-500">
								{{ ctrans("At most 30% of the minimum order amount") }}: {{ maxAmountOff }} {{ shop_data.currency_code }}
							</p>
						</div>

						<div v-if="rewardType === 'gifts'" class="space-y-2">
							<div v-for="(gift, index) in gifts" :key="index" class="flex items-center gap-2">
								<div class="flex-1">
									<PureMultiselectInfiniteScroll
										v-model="gift.product_id"
										:fetchRoute="productFetchRoute"
										valueProp="id"
										labelProp="name"
										mode="single"
										:placeholder="ctrans('Select product')" />
								</div>
								<InputNumber v-model="gift.quantity" :min="1" inputClass="w-20" />
								<Button
									v-if="gifts.length > 1"
									type="tertiary"
									icon="fal fa-trash-alt"
									:tooltip="ctrans('Remove gift')"
									@click="removeGift(index)" />
							</div>
							<Button type="tertiary" icon="fal fa-plus" :label="ctrans('Add another gift')" @click="addGift" />
						</div>
					</div>

					<div class="space-y-1">
						<label class="font-medium">{{ ctrans("Show on customer dashboard") }}</label>
						<p class="text-xs text-gray-500">{{ ctrans("Customers on the list see the voucher and their code until it ends.") }}</p>
						<Toggle v-model="showOnCustomerDashboard" />
					</div>

					<p class="text-sm text-gray-500">{{ ctrans("Each customer can use the voucher once.") }}</p>
				</section>

				<div class="flex justify-end gap-x-4">
					<Button type="cancel" @click="closeModal" />
					<Button
						full
						icon="fad fa-save"
						:label="ctrans('Create voucher')"
						:loading="isLoadingSubmit"
						:disabled="isFormInvalid || isLoadingSubmit"
						@click="submit" />
				</div>
			</div>
		</Modal>
	</div>
</template>
