<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { ref, computed, watch } from "vue"
import { DatePicker, InputNumber, RadioButton } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import PureInput from "../Pure/PureInput.vue"
import InformationIcon from "../Utils/InformationIcon.vue"
import Toggle from "../Pure/Toggle.vue"
import PureMultiselectInfiniteScroll from "../Pure/PureMultiselectInfiniteScroll.vue"
import { faSpinnerThird, faCheckCircle, faTimesCircle } from "@fas"
library.add(faSpinnerThird, faCheckCircle, faTimesCircle)
const props = defineProps<{
	shop_data: {
		id: number
		organisation: string
		offercampaign: string
		slug: string
		currency_code: string
		default_dates: {
			start: string
			end: string
		}
	}
}>()

const isOpenModal = ref(false)
const openModal = () => {
	resetForm()
	startDate.value = new Date(props.shop_data.default_dates.start)
	endDate.value = new Date(props.shop_data.default_dates.end)
	isOpenModal.value = true
}

const closeModal = () => {
	isOpenModal.value = false
	resetForm()
}
const isLoadingSubmit = ref(false)
const discountPercentage = ref<number | null>(null)
const offerVoucher = ref("")
const offerLabel = ref("")
const offerAmount = ref<number | null>(0)
const startDate = ref<Date | null>(
	props.shop_data.default_dates?.start ? new Date(props.shop_data.default_dates.start) : null
)

const endDate = ref<Date | null>(
	props.shop_data.default_dates?.end ? new Date(props.shop_data.default_dates.end) : null
)
const reuseCustomer = ref(false)

const isCheckingVoucher = ref(false)
const voucherExists = ref<boolean | null>(null)
let voucherCheckTimeout: ReturnType<typeof setTimeout> | null = null

const checkVoucherExistence = async (code: string) => {
	isCheckingVoucher.value = true
	try {
		const { data } = await axios.get(
			route("grp.org.shops.show.discounts.campaigns.check_voucher", {
				organisation: props.shop_data.organisation,
				shop: props.shop_data.slug,
				offerCampaign: props.shop_data.offercampaign,
			}),
			{ params: { code } }
		)
		voucherExists.value = data.exists
	} catch (error) {
		voucherExists.value = null
	} finally {
		isCheckingVoucher.value = false
	}
}

watch(offerVoucher, (value) => {
	voucherExists.value = null

	if (voucherCheckTimeout) {
		clearTimeout(voucherCheckTimeout)
	}

	const code = value?.trim()
	if (!code || /\s/.test(value ?? "")) {
		isCheckingVoucher.value = false
		return
	}

	voucherCheckTimeout = setTimeout(() => checkVoucherExistence(code), 400)
})

// target (single selection)
type TargetType = "shop" | "department" | "subdepartment" | "family" | "collection" | "product"
const target = ref<TargetType | null>(null)

const categoryFilters = ref<number | null>(null)
const collectionFilters = ref<number | null>(null)
const productFilters = ref<number | null>(null)

const shopId = props.shop_data.id
const shopSlug = props.shop_data.slug

type CategoryTarget = "department" | "subdepartment" | "family"

const categoryRoutes: Record<
	CategoryTarget,
	{ name: string; parameters: Record<string, unknown> }
> = {
	department: {
		name: "grp.json.shop.departments",
		parameters: { shop: shopSlug },
	},
	subdepartment: {
		name: "grp.json.shop.sub_departments",
		parameters: { shop: shopId },
	},
	family: {
		name: "grp.json.shop.families",
		parameters: { shop: shopId },
	},
}

const isCategoryTarget = (t: TargetType | null): t is CategoryTarget =>
	t === "department" || t === "subdepartment" || t === "family"

const activeCategoryRoute = computed(() =>
	isCategoryTarget(target.value) ? categoryRoutes[target.value] : null
)

const collectionRoute = {
	name: "grp.json.shop.catalogue.collections",
	parameters: { shop: shopSlug, scope: shopSlug },
}

const productFetchRoute = {
	name: "grp.json.shop.products",
	parameters: { shop: shopSlug },
}

const targetOptions: { value: TargetType; label: string }[] = [
	{ value: "shop", label: "Shop" },
	{ value: "department", label: "Department" },
	{ value: "subdepartment", label: "Sub Department" },
	{ value: "family", label: "Family" },
	{ value: "collection", label: "Collection" },
	{ value: "product", label: "Product" },
]

const today = new Date(new Date().setHours(0, 0, 0, 0))

function formatDate(date: Date | null) {
	if (!date) return null

	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, "0")
	const day = String(date.getDate()).padStart(2, "0")

	return `${year}-${month}-${day}`
}

const targetTypeMap: Record<TargetType, string> = {
	shop: "shop",
	department: "department",
	subdepartment: "sub_department",
	family: "family",
	collection: "collection",
	product: "product",
}

const buildTargetPayload = () => {
	const t = target.value
	if (!t) return null

	let id: number | null = null
	if (t === "shop") {
		id = shopId
	} else if (t === "product") {
		id = productFilters.value
	} else if (t === "collection") {
		id = collectionFilters.value
	} else if (isCategoryTarget(t)) {
		id = categoryFilters.value
	}

	if (!id) return null

	return {
		target_type: targetTypeMap[t],
		target_id: id,
	}
}

const submitVoucherOffer = () => {
	const targetPayload = buildTargetPayload()

	const payload = {
		voucher: offerVoucher.value,
		name: offerLabel.value,
		type: "amount",
		offer_amount: offerAmount.value,
		start_at: formatDate(startDate.value),
		end_at: formatDate(endDate.value),
		can_customer_reuse: reuseCustomer.value,
		percentage_off: discountPercentage.value,
		target_type: targetPayload?.target_type ?? null,
		target_id: targetPayload?.target_id ?? null,
	}

	isLoadingSubmit.value = true

	axios
		.post(
			route("grp.models.store_voucher", {
				shop: props.shop_data.id,
			}),
			payload
		)
		.then((response) => {
			notify({
				title: trans("Success"),
				text: trans("Successfully submit the data"),
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
			router.reload()
		})
		.catch((error) => {
			const errors = error.response?.data?.errors || {}
			const errMsg =
				Object.values(errors).flat().join(". ") ||
				trans("Failed to submit the data, please try again")
			notify({
				title: trans("Something went wrong"),
				text: errMsg,
				type: "error",
			})
		})
		.finally(() => {
			isLoadingSubmit.value = false
		})
}

watch(target, () => {
	categoryFilters.value = null
	collectionFilters.value = null
	productFilters.value = null
})

function resetForm() {
	offerLabel.value = ""
	offerVoucher.value = ""
	voucherExists.value = null
	isCheckingVoucher.value = false
	startDate.value = null
	endDate.value = null
	discountPercentage.value = null
	reuseCustomer.value = false
	offerAmount.value = 0
	target.value = null
	categoryFilters.value = null
	collectionFilters.value = null
	productFilters.value = null
}

const hasVoucherWhitespace = computed(() => /\s/.test(offerVoucher.value ?? ""))

const isVoucherInfoInvalid = computed(() => {
	if (hasVoucherWhitespace.value) return true
	if (voucherExists.value !== false) return true
	if (!offerVoucher.value?.trim()) return true
	if (!offerLabel.value?.trim()) return true
	if (!startDate.value) return true
	if (offerAmount.value === null || offerAmount.value === undefined || offerAmount.value < 0)
		return true
	return false
})

const isFormInvalid = computed(() => {
	if (isVoucherInfoInvalid.value) return true

	const pct = discountPercentage.value
	if (pct === null || pct === undefined || pct <= 0 || pct > 100) return true

	return buildTargetPayload() === null
})
</script>

<template>
	<div>
		<Button :label="trans('Create Voucher')" @click="openModal" icon="fas fa-badge-percent" />

		<Modal :isOpen="isOpenModal" width="w-full max-w-3xl" @close="closeModal">
			<div class="p-1 space-y-6">
				<h2 class="text-2xl font-bold mb-4 text-center">
					{{ trans("Create Voucher") }}
				</h2>

				<div class="space-y-2">
					<label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
						<FontAwesomeIcon
							icon="fas fa-asterisk"
							class="font-light text-xs text-red-400 align-middle" />

						{{ trans("Voucher code") }}:
					</label>

					<PureInput
						v-model="offerVoucher"
						:maxLength="60"
						:placeholder="trans('Enter Voucher code')" />

					<p
						v-if="hasVoucherWhitespace"
						class="text-sm text-red-500 flex items-center gap-x-1">
						<FontAwesomeIcon icon="fas fa-times-circle" class="text-xs" />
						{{ trans("Voucher code cannot contain spaces") }}
					</p>
					<p
						v-else-if="isCheckingVoucher"
						class="text-sm text-gray-500 flex items-center gap-x-1">
						<FontAwesomeIcon icon="fas fa-spinner-third" spin class="text-xs" />
						{{ trans("Checking voucher code") }}…
					</p>
					<p
						v-else-if="voucherExists === true"
						class="text-sm text-red-500 flex items-center gap-x-1">
						<FontAwesomeIcon icon="fas fa-times-circle" class="text-xs" />
						{{ trans("Voucher code already exists") }}
					</p>
					<p
						v-else-if="voucherExists === false"
						class="text-sm text-green-600 flex items-center gap-x-1">
						<FontAwesomeIcon icon="fas fa-check-circle" class="text-xs" />
						{{ trans("Voucher code is available") }}
					</p>
				</div>
				<!-- offer name -->
				<div class="space-y-2">
					<label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
						<FontAwesomeIcon
							icon="fas fa-asterisk"
							class="font-light text-xs text-red-400 align-middle" />

						{{ trans("Offer name") }}:
					</label>

					<PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
				</div>

				<!-- amount -->
				<div class="space-y-2">
					<label class="font-medium mb-2 flex items-center gap-x-1">
						<FontAwesomeIcon
							icon="fas fa-asterisk"
							class="font-light text-xs text-red-400 align-middle" />
						{{ trans("Minimum purchase amount") }}:
					</label>

					<InputNumber
						v-model="offerAmount"
						inputId="offer_amount"
						class="w-full"
						mode="currency"
						:currency="props.shop_data.currency_code"
						locale="en-US"
						:placeholder="trans('Enter minimum amount')" />
				</div>

				<!-- Start date - end date -->
				<div class="grid grid-cols-2 gap-x-6">
					<div>
						<label class="font-medium mb-2 flex items-center gap-x-1">
							{{ trans("Start date") }}
							<InformationIcon
								:information="
									trans('If start date is empty, will start immediately')
								" />:
						</label>

						<DatePicker
							v-model="startDate"
							:minDate="today"
							showButtonBar
							showIcon
							:placeholder="trans('Select start date')" />
					</div>

					<div>
						<label class="font-medium mb-2 flex items-center gap-x-1">
							{{ trans("End date") }}
							<InformationIcon
								:information="
									trans('If end date is empty, will treat as permanent')
								" />:
						</label>

						<DatePicker
							v-model="endDate"
							showButtonBar
							showIcon
							:minDate="startDate"
							:placeholder="trans('Select end date')" />
					</div>
				</div>

				<!-- target -->
				<div class="space-y-2">
					<div class="space-y-3 mb-2">
						<h3 class="text-sm text-gray-500">
							{{ trans("Choose where this voucher will apply") }}
						</h3>
						<label class="font-semibold">
							<FontAwesomeIcon
								icon="fas fa-asterisk"
								class="font-light text-xs text-red-400 align-middle" />
							{{ trans("Target") }}
						</label>

						<div class="flex flex-nowrap gap-2">
							<label
								v-for="opt in targetOptions"
								:key="opt.value"
								:for="`target-${opt.value}`"
								class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 rounded-lg border cursor-pointer transition-colors text-sm whitespace-nowrap"
								:class="
									target === opt.value
										? 'border-green-500 bg-green-50 text-green-700 font-semibold'
										: 'border-gray-200 hover:border-gray-300'
								">
								<RadioButton
									v-model="target"
									:value="opt.value"
									:inputId="`target-${opt.value}`" />
								<span>{{ trans(opt.label) }}</span>
							</label>
						</div>
					</div>

					<div v-if="activeCategoryRoute" class="space-y-2">
						<label class="font-medium">
							{{ trans("Select Item") }}
						</label>
						<PureMultiselectInfiniteScroll
							:key="target ?? 'none'"
							v-model="categoryFilters"
							:fetchRoute="activeCategoryRoute"
							valueProp="id"
							labelProp="name" />
					</div>

					<div v-if="target === 'collection' && collectionRoute" class="space-y-2">
						<label class="font-medium">
							{{ trans("Select Item") }}
						</label>
						<PureMultiselectInfiniteScroll
							v-model="collectionFilters"
							:fetchRoute="collectionRoute"
							valueProp="id"
							labelProp="name" />
					</div>

					<div v-if="target === 'product'" class="space-y-2">
						<label class="font-medium">
							{{ trans("Select Item") }}
						</label>

						<PureMultiselectInfiniteScroll
							v-model="productFilters"
							:fetchRoute="productFetchRoute"
							valueProp="id"
							labelProp="name" />
					</div>

					<!-- Section: Discount -->
					<div>
						<div class="font-medium mb-2 flex items-center gap-x-1">
							<FontAwesomeIcon
								icon="fas fa-asterisk"
								class="font-light text-xs text-red-400 align-middle" />
							{{ trans("Discount") }}:
						</div>

						<InputNumber
							v-model="discountPercentage"
							inputId="offer_discount"
							:placeholder="trans('Enter percentage')"
							suffix="%"
							:min="0"
							:max="100"
							class="w-full" />
					</div>
				</div>

                <!-- Discount -->
				<div class="space-y-2">
					<label class="font-medium flex items-center gap-x-1">
						{{ trans("Can customers reuse the voucher") }}?
					</label>

					<Toggle v-model="reuseCustomer" />
				</div>
                
				<div class="mt-8 flex justify-end gap-x-4">
					<Button @click="closeModal" type="cancel" />
					<Button
						full
						icon="fad fa-save"
						:label="isLoadingSubmit ? trans('Loading') : trans('Save')"
						@click="submitVoucherOffer"
						:loading="isLoadingSubmit"
						:disabled="isFormInvalid || isLoadingSubmit" />
				</div>
			</div>
		</Modal>
	</div>
</template>
