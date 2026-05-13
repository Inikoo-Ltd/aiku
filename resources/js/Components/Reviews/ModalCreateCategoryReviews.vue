<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Tag from "@/Components/Tag.vue"
import { computed, nextTick, onBeforeUnmount, ref } from "vue"
import type { Image as ImageProxy } from "@/types/Image"
import { router } from "@inertiajs/vue3"
import { Textarea } from "primevue"
import ImagePrime from "primevue/image"
import Select from "primevue/select"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import axios from "axios"

const props = defineProps<{
    product_category_id: number
    reviewable_id?: number
    reviewable_type?: "ProductCategory" | "Product" | "Shop"
    mode?: "create" | "update" | "detail"
    hideDefaultButton?: boolean
    buttonLabel?: string
    buttonIcon?: string
    review?: {
        id: number
        customer_id?: number | null
        contact_name?: string | null
        customer_name?: string | null
        status?: "pending" | "approved" | "rejected" | null
        rating?: number | null
        rating_a?: number | null
        rating_b?: number | null
        rating_c?: number | null
        rating_d?: number | null
        rating_e?: number | null
        message?: string | null
        created_at?: string | null
        image_thumbnail?: ImageProxy | string | null
        image_thumbnails?: Array<ImageProxy | string> | null
        image_gallery?: Array<ImageProxy | string> | null
        existing_reply?: {
            id: number
            body?: string | null
            is_public?: boolean
            status?: "pending" | "approved" | "rejected" | null
        } | null
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
    rating_labels?: Array<{
        dimension: string
        label: string
        is_required?: boolean
        weight?: number
    }>
}>()

const isOpenModal = ref(false)
const ratingByDimension = ref<Record<string, number>>({})
const message = ref("")
const isLoadingSubmit = ref(false)
const errors = ref<Record<string, string[]>>({})
const imageFiles = ref<File[]>([])
const selectedCustomerId = ref<number | null>(null)
const selectedStatus = ref<"pending" | "approved" | "rejected">("pending")
const customerOptions = ref<CustomerOption[]>([])
const customerSearch = ref("")
const customerPage = ref(1)
const customerHasMore = ref(true)
const isLoadingCustomers = ref(false)
const customerDropdownElement = ref<HTMLElement | null>(null)

type CustomerOption = {
    customer_id: number
    label: string
    contact_name?: string | null
    username?: string
    email?: string | null
}

type RawCustomerOption = {
    customer_id: number | string
    label?: string
    contact_name?: string | null
    username?: string
    email?: string | null
}

const reviewableId = computed(() => props.reviewable_id ?? props.product_category_id)

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

const dimensionRatingKeyMap: Record<string, "rating_a" | "rating_b" | "rating_c" | "rating_d" | "rating_e"> = {
    a: "rating_a",
    b: "rating_b",
    c: "rating_c",
    d: "rating_d",
    e: "rating_e",
}

const activeRatingLabels = computed(() => {
    const source = Array.isArray(props.rating_labels) ? props.rating_labels : []
    const fromDatabase = source
        .map((item) => {
            const dimension = String(item.dimension ?? "").toLowerCase()
            if (!Object.hasOwn(dimensionRatingKeyMap, dimension)) {
                return null
            }

            return {
                dimension,
                label: item.label || `Rating ${dimension.toUpperCase()}`,
            }
        })
        .filter((item): item is { dimension: string; label: string } => item !== null)
        .sort((left, right) => left.dimension.localeCompare(right.dimension))

    return fromDatabase
})

const averageRating = computed(() => {
    const values = activeRatingLabels.value
        .map((item) => Number(ratingByDimension.value[item.dimension] ?? 0))
        .filter((value) => Number.isFinite(value) && value >= 1 && value <= 5)

    if (!values.length) {
        return 5
    }

    return Math.round(values.reduce((total, value) => total + value, 0) / values.length)
})
const mainRatingStars = computed(() => "★".repeat(Math.max(0, Math.min(5, averageRating.value))))
const mainRatingEmptyStars = computed(() => "☆".repeat(Math.max(0, 5 - averageRating.value)))

const reviewStatusOptions = computed(() => [
    { value: "pending", label: trans("Pending") },
    { value: "approved", label: trans("Approved") },
    { value: "rejected", label: trans("Rejected") },
])
const reviewCustomerName = computed(() => props.review?.contact_name ?? props.review?.customer_name ?? "-")
const detailStatusValue = computed(() => (props.review?.status ?? selectedStatus.value ?? "pending"))
const detailStatusLabel = computed(() => {
    if (detailStatusValue.value === "approved") {
        return trans("Approved")
    }

    if (detailStatusValue.value === "rejected") {
        return trans("Rejected")
    }

    return trans("Pending")
})
const detailStatusTheme = computed(() => {
    if (detailStatusValue.value === "approved") {
        return 3
    }

    if (detailStatusValue.value === "rejected") {
        return 7
    }

    return 8
})
const detailDateTime = computed(() => {
    const value = props.review?.created_at
    if (!value) {
        return "-"
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return "-"
    }

    return date.toLocaleString()
})
const existingReply = computed(() => props.review?.existing_reply ?? null)
const existingReplyVisibility = computed(() => {
    if (!existingReply.value) {
        return null
    }

    return existingReply.value.is_public ? trans("Public reply") : trans("Private reply")
})
const reviewThumbnails = computed<ImageProxy[]>(() => {
    const images = props.review?.image_gallery ?? props.review?.image_thumbnails
    if (!Array.isArray(images)) {
        return []
    }

    return images
        .map((thumbnail): ImageProxy | null => {
            if (typeof thumbnail === "string" && thumbnail.length > 0) {
                return { original: thumbnail }
            }

            if (thumbnail && typeof thumbnail === "object" && typeof thumbnail.original === "string") {
                return thumbnail
            }

            return null
        })
        .filter((thumbnail): thumbnail is ImageProxy => thumbnail !== null)
})
const reviewImageUrls = computed<string[]>(() =>
    reviewThumbnails.value
        .map((image) => image.webp ?? image.original)
        .filter((url): url is string => typeof url === "string" && url.length > 0)
)
const normalizeCustomerId = (value: unknown): number | null => {
    if (typeof value === "number") {
        return Number.isFinite(value) && value > 0 ? value : null
    }

    if (typeof value === "string") {
        const parsed = Number.parseInt(value, 10)
        return Number.isFinite(parsed) && parsed > 0 ? parsed : null
    }

    return null
}

const normalizeCustomerOption = (option: RawCustomerOption): CustomerOption | null => {
    const id = normalizeCustomerId(option.customer_id)
    if (!id) {
        return null
    }

    return {
        customer_id: id,
        label: option.label ?? option.contact_name ?? option.username ?? `#${id}`,
        contact_name: option.contact_name,
        username: option.username,
        email: option.email,
    }
}

const upsertCustomerOptions = (options: CustomerOption[]): void => {
    const map = new Map<number, CustomerOption>()
    for (const option of customerOptions.value) {
        map.set(option.customer_id, option)
    }
    for (const option of options) {
        map.set(option.customer_id, option)
    }
    customerOptions.value = [...map.values()]
}

const ensureCurrentCustomerOption = (): void => {
    const currentCustomerId = normalizeCustomerId(props.review?.customer_id)
    if (!currentCustomerId) {
        return
    }

    if (customerOptions.value.some((option) => option.customer_id === currentCustomerId)) {
        return
    }

    const currentLabel = props.review?.contact_name ?? props.review?.customer_name ?? `#${currentCustomerId}`
    customerOptions.value = [
        {
            customer_id: currentCustomerId,
            label: currentLabel,
            contact_name: currentLabel,
        },
        ...customerOptions.value,
    ]
}

const hydrateInitialCustomerOptions = (): void => {
    const sourceOptions = (Array.isArray(props.customers?.data) ? props.customers.data : []) as RawCustomerOption[]
    const normalizedOptions = sourceOptions
        .map((option) => normalizeCustomerOption(option))
        .filter((option): option is CustomerOption => option !== null)

    customerOptions.value = []
    upsertCustomerOptions(normalizedOptions)
    ensureCurrentCustomerOption()
}

const fetchCustomers = async (reset: boolean): Promise<void> => {
    if (isLoadingCustomers.value) {
        return
    }

    if (reset) {
        customerPage.value = 1
        customerHasMore.value = true
        customerOptions.value = []
        ensureCurrentCustomerOption()
    }

    if (!customerHasMore.value) {
        return
    }

    isLoadingCustomers.value = true

    try {
        const response = await axios.get(
            route(
                props.reviewable_type === "Product"
                    ? "grp.models.review.customers.product"
                    : props.reviewable_type === "Shop"
                    ? "grp.models.review.customers.shop"
                    : "grp.models.review.customers",
                props.reviewable_type === "Product"
                    ? { product: reviewableId.value }
                    : props.reviewable_type === "Shop"
                    ? { shop: reviewableId.value }
                    : { productCategory: reviewableId.value }
            ),
            {
                params: {
                    page: customerPage.value,
                    per_page: 50,
                    "filter[global]": customerSearch.value || undefined,
                },
            }
        )

        const payloadOptions = Array.isArray(response?.data?.data) ? response.data.data : []
        const normalizedOptions = payloadOptions
            .map((option: RawCustomerOption) => normalizeCustomerOption(option))
            .filter((option: CustomerOption | null): option is CustomerOption => option !== null)

        upsertCustomerOptions(normalizedOptions)
        ensureCurrentCustomerOption()

        const nextPage = normalizeCustomerId(response?.data?.meta?.next_page)
        if (nextPage) {
            customerPage.value = nextPage
            customerHasMore.value = true
        } else {
            customerHasMore.value = false
        }
    } catch {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to load customers"),
            type: "error",
        })
    } finally {
        isLoadingCustomers.value = false
    }
}

const findVisibleCustomerDropdownElement = (): HTMLElement | null => {
    const selectors = [
        ".p-select-overlay .p-select-list-container",
        ".p-select-overlay .p-virtualscroller",
        ".p-select-overlay .p-scroller",
    ]

    for (const selector of selectors) {
        const elements = Array.from(document.querySelectorAll(selector)) as HTMLElement[]
        const visible = elements.find((element) => element.offsetParent !== null)
        if (visible) {
            return visible
        }
    }

    return null
}

const onCustomerDropdownScroll = (): void => {
    const element = customerDropdownElement.value
    if (!element || isLoadingCustomers.value || !customerHasMore.value) {
        return
    }

    const threshold = 24
    const reachedBottom = element.scrollTop + element.clientHeight >= element.scrollHeight - threshold
    if (reachedBottom) {
        void fetchCustomers(false)
    }
}

const detachCustomerDropdownScrollListener = (): void => {
    if (customerDropdownElement.value) {
        customerDropdownElement.value.removeEventListener("scroll", onCustomerDropdownScroll)
        customerDropdownElement.value = null
    }
}

const attachCustomerDropdownScrollListener = async (): Promise<void> => {
    await nextTick()
    detachCustomerDropdownScrollListener()
    customerDropdownElement.value = findVisibleCustomerDropdownElement()
    if (customerDropdownElement.value) {
        customerDropdownElement.value.addEventListener("scroll", onCustomerDropdownScroll)
    }
}

const onCustomerSelectShow = async (): Promise<void> => {
    await fetchCustomers(true)
    await attachCustomerDropdownScrollListener()
}

const onCustomerSelectHide = (): void => {
    detachCustomerDropdownScrollListener()
}

const onCustomerFilter = async (event: { value?: string }): Promise<void> => {
    customerSearch.value = (event?.value ?? "").trim()
    await fetchCustomers(true)
    await attachCustomerDropdownScrollListener()
}

const resetForm = (): void => {
    const nextRatings: Record<string, number> = {}
    for (const item of activeRatingLabels.value) {
        const ratingKey = dimensionRatingKeyMap[item.dimension]
        const sourceValue = ratingKey ? props.review?.[ratingKey] : null
        nextRatings[item.dimension] = Number(sourceValue ?? props.review?.rating ?? 5)
    }
    ratingByDimension.value = nextRatings
    message.value = props.review?.message ?? ""
    selectedCustomerId.value = normalizeCustomerId(props.review?.customer_id)
    const initialStatus = props.review?.status
    selectedStatus.value = initialStatus === "approved" || initialStatus === "rejected" ? initialStatus : "pending"
    customerSearch.value = ""
    customerPage.value = 1
    customerHasMore.value = true
    hydrateInitialCustomerOptions()
    imageFiles.value = []
}

const openModal = (): void => {
    errors.value = {}
    resetForm()
    isOpenModal.value = true
}

const closeModal = (): void => {
    isOpenModal.value = false
    resetForm()
    errors.value = {}
    onCustomerSelectHide()
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
    isLoadingSubmit.value = true

    const formData = new FormData()
    formData.append("reviewable_type", props.reviewable_type ?? "ProductCategory")
    formData.append("reviewable_id", String(reviewableId.value))
    formData.append("rating_main", String(averageRating.value))
    formData.append("rating", String(averageRating.value))
    formData.append("message", message.value)

    activeRatingLabels.value.forEach((item) => {
        const key = dimensionRatingKeyMap[item.dimension]
        if (!key) {
            return
        }

        const value = Number(ratingByDimension.value[item.dimension] ?? 0)
        if (Number.isFinite(value) && value >= 1 && value <= 5) {
            formData.append(key, String(value))
        }
    })

    const payloadCustomerId = selectedCustomerId.value ?? normalizeCustomerId(props.review?.customer_id)
    if (payloadCustomerId !== null) {
        formData.append("customer_id", String(payloadCustomerId))
    }

    if (selectedStatus.value) {
        formData.append("status", selectedStatus.value)
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

onBeforeUnmount(() => {
    detachCustomerDropdownScrollListener()
})
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
            <div class="max-h-[78vh] space-y-3 overflow-y-auto p-1 pr-2">
                <h2 class="text-center text-xl font-bold">{{ modalTitle }}</h2>

                <template v-if="isDetailMode">
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ trans("Ratings") }}</label>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ trans("Main rating") }}</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="text-2xl leading-none text-amber-500">
                                        <span>{{ mainRatingStars }}</span>
                                        <span class="text-amber-200">{{ mainRatingEmptyStars }}</span>
                                    </div>
                                    <div class="text-lg font-semibold text-amber-700">{{ averageRating }}/5</div>
                                </div>
                            </div>
                            <div v-if="activeRatingLabels.length" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                <div
                                    v-for="item in activeRatingLabels"
                                    :key="`detail-${item.dimension}`"
                                    class="space-y-1 rounded border border-gray-200 px-2 py-1.5"
                                >
                                    <div class="text-xs text-gray-600">{{ item.label }}</div>
                                    <div class="text-lg leading-none text-yellow-500">
                                        <span>{{ "★".repeat(Math.max(0, Math.min(5, Number(ratingByDimension[item.dimension] ?? 0)))) }}</span>
                                        <span class="text-gray-300">{{ "☆".repeat(Math.max(0, 5 - Math.max(0, Math.min(5, Number(ratingByDimension[item.dimension] ?? 0))))) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-lg font-semibold text-gray-900">{{ reviewCustomerName }}</div>
                                <Tag :theme="detailStatusTheme" :label="detailStatusLabel" />
                            </div>
                            <div class="mt-2 text-base text-gray-900">{{ message || "-" }}</div>
                            <div v-if="reviewImageUrls.length" class="mt-3 grid grid-cols-3 gap-2">
                                <ImagePrime
                                    v-for="(imageUrl, index) in reviewImageUrls"
                                    :key="`${review?.id ?? 'review'}-detail-image-${index}`"
                                    :src="imageUrl"
                                    preview
                                    imageClass="h-16 w-full rounded border border-gray-200 object-cover cursor-pointer"
                                    class="w-full"
                                />
                            </div>
                            <div class="mt-3 text-right text-xs text-gray-500">{{ detailDateTime }}</div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-3">
                            <div
                                v-if="existingReply"
                                class="space-y-1"
                            >
                                <div class="text-xs font-medium text-gray-600">{{ existingReplyVisibility }}</div>
                                <div class="max-h-24 overflow-y-auto text-sm text-gray-800">{{ existingReply.body || "-" }}</div>
                            </div>
                            <div v-else class="text-sm text-gray-500">
                                {{ trans("No reply yet") }}
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">{{ trans("Ratings") }}</label>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ trans("Main rating") }}</div>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="text-2xl leading-none text-amber-500">
                                    <span>{{ mainRatingStars }}</span>
                                    <span class="text-amber-200">{{ mainRatingEmptyStars }}</span>
                                </div>
                                <div class="text-lg font-semibold text-amber-700">{{ averageRating }}/5</div>
                            </div>
                        </div>
                        <div v-if="activeRatingLabels.length" class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <div
                                v-for="item in activeRatingLabels"
                                :key="item.dimension"
                                class="space-y-1 rounded border border-gray-200 px-2 py-1.5"
                            >
                                <div class="text-xs text-gray-600">{{ item.label }}</div>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-for="star in 5"
                                        :key="`${item.dimension}-${star}`"
                                        type="button"
                                        class="text-xl leading-none transition-colors"
                                        :class="star <= (ratingByDimension[item.dimension] ?? 0) ? 'text-yellow-500' : 'text-gray-300'"
                                        @click="ratingByDimension[item.dimension] = star"
                                    >
                                        ★
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-amber-600">
                            {{ trans("Rating labels are not configured for this shop.") }}
                        </div>
                        <div v-if="errors.rating_main?.[0]" class="text-sm text-red-500">{{ errors.rating_main[0] }}</div>
                        <div v-if="errors.rating?.[0]" class="text-sm text-red-500">{{ errors.rating[0] }}</div>
                    </div>

                    <div :class="isUpdateMode ? 'grid grid-cols-1 gap-3 md:grid-cols-2' : 'space-y-3'">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">{{ trans("Customer") }}</label>
                            <Select
                                v-model="selectedCustomerId"
                                :options="customerOptions"
                                optionLabel="label"
                                optionValue="customer_id"
                                filter
                                class="w-full"
                                :loading="isLoadingCustomers"
                                :placeholder="trans('Select customer')"
                                @show="onCustomerSelectShow"
                                @hide="onCustomerSelectHide"
                                @filter="onCustomerFilter"
                            />
                            <div v-if="errors.customer_id?.[0]" class="text-sm text-red-500">{{ errors.customer_id[0] }}</div>
                        </div>

                        <div v-if="isUpdateMode" class="space-y-2">
                            <label class="text-sm font-medium">{{ trans("Status") }}</label>
                            <Select
                                v-model="selectedStatus"
                                :options="reviewStatusOptions"
                                optionLabel="label"
                                optionValue="value"
                                class="w-full"
                                :placeholder="trans('Select status')"
                            />
                            <div v-if="errors.status?.[0]" class="text-sm text-red-500">{{ errors.status[0] }}</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">{{ trans("Message") }}</label>
                        <Textarea v-model="message" rows="4" class="w-full" />
                        <div v-if="errors.message?.[0]" class="text-sm text-red-500">{{ errors.message[0] }}</div>
                    </div>

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
                </template>

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
