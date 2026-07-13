<script setup lang="ts">
import { computed, ref, inject } from "vue"
import { InputNumber, RadioButton } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGift, faRepeat, faCubes } from "@fal"
import { faAsterisk, faFragile } from "@fas"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Image from "../../Common/Components/Image.vue"
import { routeType } from "@/types/route"
import type { UpcomingTransaction, UpcomingTransactionType } from "./upcomingTransaction"
import { upcomingTransactionTypes } from "./upcomingTransaction"

library.add(faGift, faRepeat, faCubes, faAsterisk, faFragile)

const props = defineProps<{
    shopSlug: string
    storeRoute: routeType
    transaction?: UpcomingTransaction | null
}>()

const emits = defineEmits<{
    (e: "close"): void
    (e: "saved"): void
}>()

const layout = inject("layout", {})

const isEditing = computed(() => !!props.transaction)

const initialQuantity = Number(props.transaction?.quantity ?? 1)

const quantity = ref<number | null>(initialQuantity)
const isCutView = ref(initialQuantity % 1 !== 0)
const productId = ref<number | null>(props.transaction?.product_id ?? null)
const type = ref<UpcomingTransactionType>(props.transaction?.type ?? "gift")
const privateNotes = ref(props.transaction?.private_notes ?? "")
const publicNotes = ref(props.transaction?.public_notes ?? "")
const selectedProduct = ref<any | null>(null)
const isLoadingSubmit = ref(false)

const productFetchRoute = {
    name: "grp.json.shop.products_including_not_for_sale",
    parameters: {
        shop: props.shopSlug,
    },
}

const initialProductOptions = computed(() =>
    props.transaction
        ? [
            {
                id: props.transaction.product_id,
                code: props.transaction.product_code,
                name: props.transaction.product_name,
            },
        ]
        : []
)

const productCode = computed(() =>
    selectedProduct.value ? selectedProduct.value.code ?? "" : props.transaction?.product_code ?? ""
)
const productName = computed(() =>
    selectedProduct.value ? selectedProduct.value.name ?? "" : props.transaction?.product_name ?? ""
)
const productImage = computed(() => selectedProduct.value?.web_images?.main?.original ?? null)

const productUnits = computed(() => {
    const parsed = Number(selectedProduct.value?.units ?? props.transaction?.product_units)

    return parsed > 0 ? parsed : 1
})

const quantityUnits = computed({
    get: () => Math.round((Number(quantity.value) || 0) * productUnits.value),
    set: (units: number | null) => {
        quantity.value = Number((Math.max(0, Number(units) || 0) / productUnits.value).toFixed(6))
    },
})

const onSelectProduct = (product: any) => {
    selectedProduct.value = product
}

const toggleCutView = () => {
    if (isCutView.value) {
        quantity.value = Math.max(1, Math.ceil(Number(quantity.value) || 1))
    }

    isCutView.value = !isCutView.value
}

const unitsFraction = computed((): [number, [number, number]] => [
    Math.floor(quantityUnits.value / productUnits.value),
    [quantityUnits.value % productUnits.value, productUnits.value],
])

const isFormInvalid = computed(
    () => !productId.value
        || Number(quantity.value) <= 0
        || (isCutView.value && productUnits.value <= 1)
)

const submit = () => {
    if (isFormInvalid.value) {
        return
    }

    const payload = {
        product_id: productId.value,
        quantity: Number(quantity.value),
        type: type.value,
        public_notes: publicNotes.value.trim() || null,
        private_notes: privateNotes.value.trim() || null,
    }

    const request = props.transaction
        ? axios.patch(route(props.transaction.update.name, props.transaction.update.parameters), payload)
        : axios.post(route(props.storeRoute.name, props.storeRoute.parameters), payload)

    isLoadingSubmit.value = true

    request
        .then(() => {
            notify({
                title: trans("Success"),
                text: isEditing.value
                    ? trans("Upcoming transaction updated")
                    : trans("Upcoming transaction created"),
                type: "success",
            })
            emits("saved")
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
</script>

<template>
    <div class="p-1 space-y-5">
        <div class="space-y-1">
            <h2 class="text-xl font-bold text-gray-900">
                {{ isEditing ? trans("Edit Upcoming Transaction") : trans("Add Upcoming Transaction") }}
            </h2>
            <p class="text-sm text-gray-500">
                {{ trans("Reserve a product to be sent as a gift or added to the customer's next order.") }}
            </p>
        </div>

        <div class="space-y-2">
            <label class="font-medium flex items-center gap-x-1 text-sm text-gray-700">
                <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                {{ trans("Select product") }}
            </label>

            <PureMultiselectInfiniteScroll
                v-model="productId"
                :fetchRoute="productFetchRoute"
                :initOptions="initialProductOptions"
                labelProp="name"
                valueProp="id"
                mode="single"
                :required="true"
                :placeholder="trans('Select product')"
                @selectedObject="onSelectProduct"
            >
                <template #singlelabel="{ value }">
                    <div class="w-full text-left pl-4 leading-4 truncate mr-2">
                        {{ value.code }}
                        <span class="text-sm text-gray-400">({{ value.name }})</span>
                        <span v-if="value.stock !== undefined" class="text-sm text-gray-400">
                            · {{ trans("Stock") }}: {{ value.stock ?? 0 }}
                        </span>
                    </div>
                </template>

                <template #option="{ option, isSelected }">
                    <div class="flex w-full items-center justify-between gap-x-2">
                        <div>
                            {{ option.code }}
                            <span class="text-sm" :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">
                                ({{ option.name }})
                            </span>
                        </div>
                        <span
                            class="text-sm whitespace-nowrap"
                            :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'"
                        >
                            {{ trans("Stock") }}: {{ option.stock ?? 0 }}
                        </span>
                    </div>
                </template>
            </PureMultiselectInfiniteScroll>

            <div v-if="productId" class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md bg-white ring-1 ring-gray-200 flex items-center justify-center">
                    <Image v-if="productImage" :src="productImage" alt="Product image" imageCover />
                    <FontAwesomeIcon v-else icon="fal fa-cubes" class="text-gray-300 text-lg" />
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-800 truncate">{{ productCode }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ productName }}</div>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <label for="upcoming_quantity" class="font-medium flex items-center gap-x-1 text-sm text-gray-700">
                <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                {{ trans("Quantity") }}
            </label>

            <div class="flex items-center gap-x-2">
                <InputNumber
                    v-if="isCutView"
                    v-model="quantityUnits"
                    inputId="upcoming_quantity"
                    showButtons
                    :min="1"
                    :step="1"
                    :suffix="` /${productUnits}`"
                    class="w-48"
                    inputClass="w-full"
                    :placeholder="trans('Enter units')"
                    key="units"
                />
                <InputNumber
                    v-else
                    v-model="quantity"
                    inputId="upcoming_quantity"
                    showButtons
                    :min="0"
                    :minFractionDigits="0"
                    :maxFractionDigits="6"
                    class="w-48"
                    inputClass="w-full"
                    :placeholder="trans('Enter quantity')"
                    key="packs"
                />                
                <span
                    v-if="layout?.app?.environment == 'local'"
                    @click="toggleCutView"
                    v-tooltip="trans('Cut view')"
                    class="text-lg align-middle opacity-60 cursor-pointer hover:opacity-100 flex items-center"
                    :class="isCutView ? 'text-orange-500' : ''"
                >
                    <FontAwesomeIcon icon="fas fa-fragile" fixed-width aria-hidden="true" />
                </span>
            </div>

            <div v-if="isCutView" class="text-xs flex items-center gap-x-1" :class="productUnits > 1 ? 'text-gray-500' : 'text-amber-600'">
                =
                <FractionDisplay :fractionData="unitsFraction" />
                {{ trans("of a pack of :units units", { units: String(productUnits) }) }}
                <span v-if="productUnits <= 1">
                    · {{ trans("this product has only 1 unit per pack, so it cannot be split") }}
                </span>
            </div>
        </div>

        <div class="space-y-2">
            <div class="font-medium flex items-center gap-x-1 text-sm text-gray-700">
                <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                {{ trans("Transaction type") }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label
                    v-for="option in upcomingTransactionTypes"
                    :key="option.value"
                    :for="`upcoming-type-${option.value}`"
                    class="flex items-start gap-3 px-3 py-3 rounded-lg border cursor-pointer transition-colors"
                    :class="type === option.value ? option.selectedClass : 'border-gray-200 hover:border-gray-300'"
                >
                    <RadioButton
                        v-model="type"
                        :inputId="`upcoming-type-${option.value}`"
                        name="upcoming_transaction_type"
                        :value="option.value"
                    />
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon :icon="option.icon" :class="option.iconClass" />
                            <span>{{ trans(option.label) }}</span>
                        </div>
                        <p class="mt-1 text-xs font-normal text-gray-500">{{ trans(option.description) }}</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <div class="col">
                    <label for="upcoming_notes" class="font-medium text-sm text-gray-700 block">
                        {{ trans("Public Note") }}
                    </label>

                    <PureTextarea
                        v-model="publicNotes"
                        full
                        inputName="upcoming_notes"
                        :rows="3"
                        :maxlength="500"
                        :placeholder="trans('Add a note for the warehouse or customer service...')"
                    />
                </div>
                <div class="col">
                    <label for="upcoming_notes" class="font-medium text-sm text-gray-700 block">
                        {{ trans("Private Note") }}
                    </label>

                    <PureTextarea
                        v-model="privateNotes"
                        full
                        inputName="upcoming_notes"
                        :rows="3"
                        :maxlength="500"
                        :placeholder="trans('Add a note for the warehouse or customer service...')"
                    />
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-x-3 pt-2">
            <Button type="cancel" :disabled="isLoadingSubmit" @click="emits('close')" />
            <Button
                icon="fad fa-save"
                :label="isEditing ? trans('Update') : trans('Save')"
                :loading="isLoadingSubmit"
                :disabled="isFormInvalid || isLoadingSubmit"
                @click="submit"
            />
        </div>
    </div>
</template>
