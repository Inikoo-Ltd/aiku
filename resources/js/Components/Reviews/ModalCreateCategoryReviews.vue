<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import SelectInfiniteScroll from "@/Components/Forms/Fields/SelectInfiniteScroll.vue"
import { computed, reactive, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { Textarea } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import axios from "axios"

const props = defineProps<{
    product_category_id: number
    reviewable_type?: "ProductCategory" | "Product"
    mode?: "create" | "update" | "detail"
    hideDefaultButton?: boolean
    buttonLabel?: string
    buttonIcon?: string
    review?: {
        id: number
        customer_id?: number | null
        contact_name?: string | null
        customer_name?: string | null
        rating?: number | null
        message?: string | null
    }
    customers?: {
        data: Array<{
            customer_id: number
            label: string
            contact_name?: string | null
            username?: string
            email?: string | null
        }>
        meta?: {
            current_page?: number
            per_page?: number
            next_page?: number | null
            has_more?: boolean
        }
    }
}>()

const isOpenModal = ref(false)
const rating = ref(5)
const message = ref("")
const isLoadingSubmit = ref(false)
const errors = ref<Record<string, string[]>>({})
const imageFiles = ref<File[]>([])
type CustomerOption = {
    customer_id: number
    label?: string
    contact_name?: string | null
    username?: string
    email?: string | null
}
const customerForm = reactive({
    customer_id: null as CustomerOption | null,
    errors: {} as Record<string, string | null>,
    recentlySuccessful: false,
})
const mode = computed(() => props.mode ?? "create")
const isDetailMode = computed(() => mode.value === "detail")
const isUpdateMode = computed(() => mode.value === "update")
const modalTitle = computed(() => {
    if (isDetailMode.value) {
        return trans("Review Detail")
    }
    if (isUpdateMode.value) {
        return trans("Update Review")
    }
    return trans("Create New Review")
})
const customerOptions = computed<CustomerOption[]>(() => {
    const baseOptions = Array.isArray(props.customers?.data) ? [...props.customers.data] : []
    const currentCustomerId = props.review?.customer_id

    if (!currentCustomerId) {
        return baseOptions
    }

    if (baseOptions.some((option) => option.customer_id === currentCustomerId)) {
        return baseOptions
    }

    const currentContactName = props.review?.contact_name ?? props.review?.customer_name
    if (!currentContactName) {
        return baseOptions
    }

    return [
        {
            customer_id: currentCustomerId,
            label: currentContactName,
            contact_name: currentContactName,
        },
        ...baseOptions,
    ]
})

const resetForm = (): void => {
    rating.value = Number(props.review?.rating ?? 5)
    message.value = props.review?.message ?? ""
    const currentCustomerId = props.review?.customer_id
    customerForm.customer_id = currentCustomerId
        ? customerOptions.value.find((option) => option.customer_id === currentCustomerId) ?? null
        : null
    imageFiles.value = []
}

const openModal = (): void => {
    errors.value = {}
    customerForm.errors = {}
    resetForm()
    isOpenModal.value = true
}

const closeModal = (): void => {
    isOpenModal.value = false
    resetForm()
    errors.value = {}
    customerForm.errors = {}
}

const onSelectImages = (event: Event): void => {
    const target = event.target as HTMLInputElement
    imageFiles.value = target.files ? Array.from(target.files) : []
}

const submitReview = (): void => {
    if (isDetailMode.value) {
        return
    }

    errors.value = {}
    customerForm.errors = {}
    isLoadingSubmit.value = true

    const formData = new FormData()
    formData.append("reviewable_type", props.reviewable_type ?? "ProductCategory")
    formData.append("reviewable_id", String(props.product_category_id))
    formData.append("rating", String(rating.value))
    formData.append("message", message.value)

    if (customerForm.customer_id?.customer_id) {
        formData.append("customer_id", String(customerForm.customer_id.customer_id))
    }

    imageFiles.value.forEach((file, index) => {
        formData.append(`images[${index}]`, file)
    })

    const endpoint = isUpdateMode.value && props.review?.id
        ? route("grp.models.review.update", { review: props.review.id })
        : route("grp.models.review.store")
    const request = isUpdateMode.value ? axios.patch(endpoint, formData, {
        headers: {
            "Content-Type": "multipart/form-data",
        },
    }) : axios.post(endpoint, formData, {
        headers: {
            "Content-Type": "multipart/form-data",
        },
    })

    request.then(() => {
            notify({
                title: trans("Success"),
                text: isUpdateMode.value ? trans("Review updated successfully") : trans("Review created successfully"),
                type: "success",
            })
            router.reload()
            closeModal()
        })
        .catch((error) => {
            errors.value = error.response?.data?.errors ?? {}
            customerForm.errors = {
                customer_id: errors.value.customer_id?.[0] ?? null,
            }
            notify({
                title: trans("Something went wrong"),
                text: trans("Failed to create review"),
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
        <slot name="trigger" :openModal="openModal">
            <Button
                v-if="!hideDefaultButton"
                :label="buttonLabel ?? trans('Create New Review')"
                :icon="buttonIcon ?? 'fas fa-star'"
                @click="openModal"
            />
        </slot>

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="space-y-4 p-1">
                <h2 class="text-center text-2xl font-bold">{{ modalTitle }}</h2>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Rating") }}</label>
                    <div class="flex items-center gap-2">
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            class="text-2xl leading-none transition-colors"
                            :class="star <= rating ? 'text-yellow-500' : 'text-gray-300'"
                            :disabled="isDetailMode"
                            @click="!isDetailMode && (rating = star)"
                        >
                            ★
                        </button>
                    </div>
                    <div v-if="errors.rating?.[0]" class="text-sm text-red-500">{{ errors.rating[0] }}</div>
                </div>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Customer") }}</label>
                    <SelectInfiniteScroll
                        :form="customerForm"
                        fieldName="customer_id"
                        :options="customerOptions as any"
                        :fieldData="{
                            fetchRoute: {
                                name: 'grp.models.review.customers',
                                parameters: { productCategory: props.product_category_id }
                            },
                            placeholder: trans('Select customer'),
                            labelProp: 'contact_name',
                            valueProp: 'customer_id',
                            searchable: true,
                            readonly: isDetailMode
                        }"
                    />
                    <div v-if="errors.customer_id?.[0]" class="text-sm text-red-500">{{ errors.customer_id[0] }}</div>
                </div>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Message") }}</label>
                    <Textarea v-model="message" rows="5" class="w-full" :disabled="isDetailMode" />
                    <div v-if="errors.message?.[0]" class="text-sm text-red-500">{{ errors.message[0] }}</div>
                </div>

                <div v-if="!isDetailMode" class="space-y-2">
                    <label class="font-medium">{{ trans("Images") }}</label>
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        @change="onSelectImages"
                    >
                    <div v-if="imageFiles.length" class="text-xs text-gray-500">{{ imageFiles.length }} {{ trans("file selected") }}</div>
                    <div v-if="errors.images?.[0]" class="text-sm text-red-500">{{ errors.images[0] }}</div>
                    <div v-if="errors['images.0']?.[0]" class="text-sm text-red-500">{{ errors['images.0'][0] }}</div>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="cancel" @click="closeModal" />
                    <Button
                        v-if="!isDetailMode"
                        :label="isUpdateMode ? trans('Update') : trans('Save')"
                        :isLoading="isLoadingSubmit"
                        :disabled="isLoadingSubmit"
                        @click="submitReview"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
