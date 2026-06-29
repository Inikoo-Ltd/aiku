<script setup lang="ts">

import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { ref, computed, watch, nextTick } from "vue"
import { DatePicker, InputNumber, RadioButton } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { router } from "@inertiajs/vue3"
import PureInput from "../Pure/PureInput.vue"
import PureMultiselectInfiniteScroll from "../Pure/PureMultiselectInfiniteScroll.vue"
import InformationIcon from "../Utils/InformationIcon.vue"
import axios from "axios"

const props = defineProps<{
    shop_data: {
        id: number
        slug: string
        currency_code: string
        organisation?: string
        offercampaign?: string
        default_dates: {
            start: string
            end: string
        }
    }
}>()

const isOpenModal = ref(false)

const offerLabel = ref("")
const offerAmount = ref<number | null>(0)

const isLoadingSubmit = ref(false)

const startDate = ref<Date | null>(
    props.shop_data.default_dates?.start
        ? new Date(props.shop_data.default_dates.start)
        : null
)

const endDate = ref<Date | null>(
    props.shop_data.default_dates?.end
        ? new Date(props.shop_data.default_dates.end)
        : null
)

const today = new Date(new Date().setHours(0, 0, 0, 0))

const quickApplyPresets = [1, 2, 3, 7]
const quickApplyDays = ref<number | null>(null)

let isApplyingPreset = false

const applyQuickApply = (days: number) => {
    isApplyingPreset = true

    const start = startDate.value ? new Date(startDate.value) : new Date(today)
    const end = new Date(start)
    end.setDate(end.getDate() + days)

    startDate.value = start
    endDate.value = end
    quickApplyDays.value = days

    nextTick(() => {
        isApplyingPreset = false
    })
}

watch([startDate, endDate], () => {
    if (!isApplyingPreset) {
        quickApplyDays.value = null
    }
})

const resetForm = () => {
    offerLabel.value = ""
    offerAmount.value = 0
    target.value = "shop"
    selectedItemId.value = null
    startDate.value = null
    endDate.value = null
    quickApplyDays.value = null
    isLoadingSubmit.value = false
}

const openModal = () => {
    startDate.value = new Date(props.shop_data.default_dates.start)
    endDate.value = new Date(props.shop_data.default_dates.end)
    isOpenModal.value = true
}

const closeModal = () => {
    isOpenModal.value = false
    resetForm()
}

function formatDate(date: Date | null) {
    if (!date) return null

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

// target (single selection)
type TargetType = "shop" | "department" | "subdepartment" | "family" | "collection" | "product"
type ItemTarget = Exclude<TargetType, "shop">

const target = ref<TargetType>("shop")
const selectedItemId = ref<number | null>(null)

const shopId = props.shop_data.id
const shopSlug = props.shop_data.slug

const itemRoutes: Record<ItemTarget, { name: string; parameters: Record<string, unknown> }> = {
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
	collection: {
		name: "grp.json.shop.catalogue.collections",
		parameters: { shop: shopSlug, scope: shopSlug },
	},
	product: {
		name: "grp.json.shop.products_including_not_for_sale",
		parameters: { shop: shopSlug },
	},
}

const requiresItemSelection = computed(() => target.value !== "shop")

const activeItemRoute = computed(() =>
	requiresItemSelection.value ? itemRoutes[target.value as ItemTarget] : null
)

const targetOptions: { value: TargetType; label: string }[] = [
	{ value: "shop", label: "Shop" },
	{ value: "department", label: "Department" },
	{ value: "subdepartment", label: "Sub Department" },
	{ value: "family", label: "Family" },
	{ value: "collection", label: "Collection" },
	{ value: "product", label: "Product" },
]

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
	const id = t === "shop" ? shopId : selectedItemId.value

	if (!id) return null

	return {
		target_type: targetTypeMap[t],
		target_id: id,
	}
}

const isFormInvalid = computed(() => {
    if (!offerLabel.value) return true
    if (requiresItemSelection.value && !selectedItemId.value) return true
    if (offerAmount.value === null || offerAmount.value === undefined || offerAmount.value < 0) return true
    if (!endDate.value) return true
    if (startDate.value && endDate.value < startDate.value) return true
    return false
})

const submitShippingOffer = () => {
    isLoadingSubmit.value = true
    const targetPayload = buildTargetPayload()
    // Section: Submit
    const payload = {
        name: offerLabel.value,
        min_order_amount: offerAmount.value,
        start_at: formatDate(startDate.value),
        end_at: formatDate(endDate.value),
        target_type: targetPayload?.target_type ?? null,
		target_id: targetPayload?.target_id ?? null,
    }

    axios.post(
        route("grp.models.shipping_offer.store", {
            shop: props.shop_data.id
        }),
        payload
    )
    .then((response) => {
        notify({
            title: trans("Success"),
            text: trans("Successfully submit the data"),
            type: "success"
        })
        resetForm();
        isOpenModal.value = false

        router.visit(route('grp.org.shops.show.discounts.campaigns.offer.show', {
            organisation: props.shop_data.organisation,
            shop: props.shop_data.slug,
            offerCampaign: props.shop_data.offercampaign,
            offer: response.data.slug
        }))
        router.reload()
    })
    .catch((error) => {
        const errors = error.response?.data?.errors || {}
        const errMsg = Object.values(errors).join('. ') || trans("Failed to submit the data, please try again");
        notify({
            title: trans("Something went wrong"),
            text: errMsg,
            type: "error"
        })
    })
    .finally(() => {
        isLoadingSubmit.value = false
    })
}

watch(target, () => {
	selectedItemId.value = null
})
</script>

<template>
    <div>
        <Button :label="trans('Create Discount Shipping')" @click="openModal" icon="fas fa-badge-percent" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-3xl" @close="closeModal">
            <div class="p-1 space-y-6">
                <h2 class="text-2xl font-bold mb-4 text-center">{{ trans("Create Discount Shipping") }}</h2>
                
                <!-- offer name -->
                <div class="space-y-2">
                    <label for="amount" class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk"
                                            class="font-light text-xs text-red-400 align-middle" />

                        {{ trans("Offer name") }}:
                    </label>

                    <PureInput v-model="offerLabel" :placeholder="trans('Enter offer name')" />
                </div>

                <!-- target -->
                <div class="space-y-2">
                    <div class="space-y-3 mb-2">
                        <h3 class="text-sm text-gray-500">
                            {{ trans("Choose where this offer will apply") }}
                        </h3>
                        <label class="font-semibold">
                            <FontAwesomeIcon
                                icon="fas fa-asterisk"
                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans("Trigger") }}
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="opt in targetOptions"
                                :key="opt.value"
                                :for="`target-${opt.value}`"
                                class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-sm whitespace-nowrap"
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

                    <div v-if="requiresItemSelection && activeItemRoute" class="space-y-2 !mt-3">
                        <label class="font-medium">
                            {{ trans("Select Item") }}
                        </label>
                        <PureMultiselectInfiniteScroll
                            :key="target"
                            v-model="selectedItemId"
                            :fetchRoute="activeItemRoute"
                            valueProp="id"
                            labelProp="name"
                            :placeholder="trans('Select from the list')" />
                    </div>
                </div>
                <!-- amount -->
                <div class="space-y-2">
                    <label class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk"
                                            class="font-light text-xs text-red-400 align-middle" />
                        {{ trans("Minimum purchase amount") }}:
                    </label>

                    <InputNumber v-model="offerAmount" inputId="offer_amount" class="w-full" mode="currency"
                                    :currency="props.shop_data.currency_code" locale="en-US"
                                    :placeholder="trans('Enter minimum amount')" />                    
                </div>

                <!-- Quick apply -->
                <div class="space-y-2">
                    <label class="font-medium mb-2 flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-bolt"
                                            class="text-xs text-amber-400 align-middle" />
                        {{ ctrans("Quick apply duration") }}
                        <InformationIcon :information="ctrans('Select a preset to automatically set the offer duration (start-end)')" />
                        :
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="days in quickApplyPresets" :key="days" type="button"
                                @click="applyQuickApply(days)"
                                class="px-3.5 py-2 rounded-lg border text-sm cursor-pointer transition-colors"
                                :class="quickApplyDays === days
                                    ? 'border-green-500 bg-green-50 text-green-700 font-semibold'
                                    : 'border-gray-200 hover:border-gray-300'">
                            {{ ctrans(':count day', { count: String(days) }) }}
                        </button>
                    </div>
                </div>

                <!-- Start date - end date -->
                <div class="grid grid-cols-2 gap-x-6 ">
                    <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                                class="font-light text-xs text-red-400 align-middle" />
                            {{ ctrans("Start date") }}
                            <InformationIcon
                                :information="trans('If start date is empty, will start immediately')" />
                            :
                        </label>
                        
                        <DatePicker v-model="startDate" showButtonBar showIcon />
                    </div>

                    <div>
                        <label class="font-medium mb-2 flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fas fa-asterisk"
                                                class="font-light text-xs text-red-400 align-middle" />
                            {{ trans("End date") }}
                            <InformationIcon
                                :information="trans('If end date is empty, will treat as permanent')" />
                            :
                        </label>
                        
                        <DatePicker v-model="endDate" showButtonBar showIcon :minDate="startDate ?? undefined"/>
                        
                        <p v-if="startDate && endDate && endDate < startDate" class="text-red-500 text-sm">
                            End date must be after start date
                        </p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button @click="closeModal" type="cancel" />
                    <Button full icon="fad fa-save" :label="isLoadingSubmit ? trans('Loading') : trans('Save')" @click="submitShippingOffer"
                            :loading="isLoadingSubmit" :disabled="isFormInvalid || isLoadingSubmit" />
                </div>

            </div>
        </Modal>
    </div>
</template>
