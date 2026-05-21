<script setup lang="ts">
import { computed, watch, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Rating from "primevue/rating"
import Textarea from "primevue/textarea"
import Icon from "@/Components/Icon.vue"
import type { Image as ImageProxy } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import PureMultiselectInfiniteScroll from "../Pure/PureMultiselectInfiniteScroll.vue"
import Organisation from "@/Pages/Grp/Organisations/Organisation.vue"
import Image from "@/Common/Components/Image.vue"

interface SchemaItem {
    dimension: string
    label?: string
    is_required?: boolean
    weight?: number
}

type SchemaPayload =
    | SchemaItem[]
    | {
        shop_reviews?: SchemaItem[]
        product_reviews?: SchemaItem[]
        product_category_reviews?: SchemaItem[]
    }

const props = defineProps<{
    type: string
    schema: SchemaPayload
    use_customer?: boolean
    modelValue:
    | {
        status?: "pending" | "approved" | "rejected" | null
        rating?: number | null
        rating_a?: number | null
        rating_b?: number | null
        rating_c?: number | null
        rating_d?: number | null
        rating_e?: number | null
        message?: string | null
        image_thumbnail?: ImageProxy | string | null
        images?: File[] | null
        review_images? : any
    }
    | null
}>()

const emit = defineEmits<{
    (event: "update:modelValue", value: typeof props.modelValue): void
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)
const fileErrors = ref<string | null>(null)
const imagePreviews = ref<{ file: File; objectURL: string }[]>([])
const maxImageCount = 3
const maxTotalBytes = 20 * 1024 * 1024

const form = useForm({
    message: props.modelValue?.message ?? "",
    rating_a: props.modelValue?.rating_a ?? null,
    rating_c: props.modelValue?.rating_c ?? null,
    rating_b: props.modelValue?.rating_b ?? null,
    rating_d: props.modelValue?.rating_d ?? null,
    rating_e: props.modelValue?.rating_e ?? null,
    rating: props.modelValue?.rating ?? null,
    status: props.modelValue?.status ?? 'approved',
    images: props.modelValue?.images ?? [],
    customer_id: props.modelValue?.customer_id ?? null,
})

const totalSize = computed(() =>
    (form.images ?? []).reduce(
        (acc, file) => acc + (file?.size ?? 0),
        0
    )
)

const selectedImageCount = computed(() => (form.images ?? []).length)
const totalSizeText = computed(() => formatSize(totalSize.value))

const chooseFiles = () => {
    fileInputRef.value?.click()
}

const clearImages = () => {
    imagePreviews.value.forEach((item) => URL.revokeObjectURL(item.objectURL))
    imagePreviews.value = []
    form.images = []
    fileErrors.value = null
    if (fileInputRef.value) {
        fileInputRef.value.value = ""
    }
}

const removeImage = (index: number) => {
    const [removed] = imagePreviews.value.splice(index, 1)

    if (removed) {
        URL.revokeObjectURL(removed.objectURL)
    }

    form.images = imagePreviews.value.map((item) => item.file)
}

const handleFileInputChange = (event: Event) => {
    const input = event.target as HTMLInputElement

    if (!input.files) {
        return
    }

    const selectedFiles = Array.from(input.files)
    const totalSelectedBytes = selectedFiles.reduce(
        (acc, file) => acc + file.size,
        0
    )

    if (selectedFiles.length > maxImageCount) {
        fileErrors.value = trans(
            "Please upload no more than {count} images.",
            { count: maxImageCount }
        )
        input.value = ""
        return
    }

    if (totalSelectedBytes > maxTotalBytes) {
        fileErrors.value = trans(
            "Total image size must not exceed {size}.",
            { size: "20 MB" }
        )
        input.value = ""
        return
    }

    fileErrors.value = null

    imagePreviews.value.forEach((item) => URL.revokeObjectURL(item.objectURL))
    imagePreviews.value = selectedFiles.map((file) => ({
        file,
        objectURL: URL.createObjectURL(file),
    }))
    form.images = selectedFiles
}

const ratingKeyMap = {
    a: "rating_a",
    b: "rating_b",
    c: "rating_c",
    d: "rating_d",
    e: "rating_e",
} as const

const normalizedSchema = computed<SchemaItem[]>(() => {
    const items = Array.isArray(props.schema)
        ? props.schema
        : [
            ...(props.schema ?? []),
        ]

    return items.filter(
        (item, index, self) =>
            index ===
            self.findIndex(
                (x) =>
                    String(x.dimension).toLowerCase() ===
                    String(item.dimension).toLowerCase()
            )
    )
})

const activeRatings = computed(() => {
    const grouped = new Map()
    normalizedSchema.value.forEach((item) => {
        const dimension = String(
            item.dimension ?? ""
        ).toLowerCase() as keyof typeof ratingKeyMap

        const field = ratingKeyMap[dimension]

        if (!field || grouped.has(dimension)) {
            return
        }

        grouped.set(dimension, {
            dimension,
            field,
            label:
                item.label?.trim() ||
                `Rating ${dimension.toUpperCase()}`,
            required: item.is_required ?? false,
        })
    })

    return ["a", "b", "c", "d", "e"]
        .map((key) => grouped.get(key))
        .filter(Boolean)
})

const averageRating = computed(() => {
    if (activeRatings.value.length) {
        const values = activeRatings.value
            .map((item) => Number(form[item.field]))
            .filter(
                (value) =>
                    !Number.isNaN(value) &&
                    value > 0
            )

        if (!values.length) {
            return null
        }

        const total = values.reduce(
            (acc, value) => acc + value,
            0
        )

        return Number(
            (total / values.length).toFixed(1)
        )

    } else
        return form.rating

})

const formatSize = (bytes: number) => {
    const k = 1024
    const dm = 2
    const sizes = ["B", "KB", "MB", "GB"]

    if (bytes === 0) {
        return "0 B"
    }

    const i = Math.floor(Math.log(bytes) / Math.log(k))
    const formattedSize = parseFloat((bytes / Math.pow(k, i)).toFixed(dm))

    return `${formattedSize} ${sizes[i]}`
}

watch(
    averageRating,
    (value) => {
        form.rating = value
    },
    {
        immediate: true,
    }
)

watch(
    () => ({
        message: form.message,
        rating_a: form.rating_a,
        rating_b: form.rating_b,
        rating_c: form.rating_c,
        rating_d: form.rating_d,
        rating_e: form.rating_e,
        rating: form.rating,
        images: form.images,
        customer_id: form.customer_id,
    }),
    (value) => {
        emit("update:modelValue", {
            ...props.modelValue,
            ...value,
            status: "approved",
        })
    },
    {
        deep: true,
        immediate: true,
    }
)
</script>

<template>
    <div class="space-y-4 ">
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 to-white p-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ trans("Write a Review") }}
                </h2>

                <p class="mt-0.5 text-sm text-gray-500">
                    {{
                        trans(
                            "Share your experience with this product"
                        )
                    }}
                </p>
            </div>

            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 rating">
                <div>
                    <div class="text-2xl font-bold leading-none text-gray-900">
                        {{ activeRatings.length ? averageRating || 0 : form.rating ?? "0.0" }}
                    </div>

                    <div class="mt-1 text-[11px] text-gray-500">
                        {{ trans("Average Rating") }}
                    </div>
                </div>

                <Rating :modelValue="activeRatings.length ? averageRating || 0 : form.rating"
                    :readonly="activeRatings.length > 0" :cancel="false" @update:model-value="(e) => form.rating = e" />
            </div>
        </div>

        <div class="space-y-3">
            <template v-if="activeRatings.length">
                <div v-for="item in activeRatings" :key="item.dimension"
                    class=" rating flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 transition-all duration-200 hover:border-gray-200 hover:bg-white sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-[11px] font-bold uppercase text-white">
                            {{ item.dimension }}
                        </div>

                        <div>
                            <div class="text-sm font-medium leading-none text-gray-800">
                                {{ item.label }}
                            </div>

                            <div v-if="item.required" class="mt-1 text-[11px] text-red-500">
                                {{ trans("Required") }}
                            </div>
                        </div>
                    </div>

                    <Rating v-model="form[item.field]" :cancel="false" />
                </div>
            </template>


            <div v-if="use_customer" class="space-y-2 pt-1">
                <label class="text-sm font-medium text-gray-800">
                    {{ trans("Customer") }}
                </label>

                <PureMultiselectInfiniteScroll v-model="form.customer_id" :fetchRoute="{
                    name: 'grp.org.shops.show.crm.customers.index',
                    parameters: {
                        organisation: route().params.organisation,
                        shop: route().params.shop,
                    },
                }" label="name" trackBy="id" valueProp="id" searchable :canClear="true" :closeOnSelect="true"
                    :placeholder="trans('Select customer')" \ />
            </div>

            <div class="space-y-2 pt-1">
                <label class="text-sm font-medium text-gray-800">
                    {{ trans("Your Review") }}
                </label>

                <Textarea v-model="form.message" rows="4" :autoResize="true"
                    :placeholder="trans('Tell people what you liked or disliked...')" class="w-full rounded-xl" />
            </div>


            <div class="space-y-2 pt-1">
                <label class="text-sm font-medium text-gray-800">
                    {{ trans("Your Image") }}
                </label>

                <p class="text-sm text-gray-500">
                    {{ trans("Upload up to 3 images, total max 20 MB. Accepted formats: JPG, PNG, GIF.") }}
                </p>

                <div class="rounded-2xl border border-gray-200 bg-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="chooseFiles"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                <Icon :data="{ icon: 'fal fa-images', class: 'text-sm text-gray-500' }" />
                                {{ trans("Choose images") }}
                            </button>

                            <button type="button" @click="clearImages" :disabled="selectedImageCount === 0"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50">
                                <Icon :data="{ icon: 'fal fa-times', class: 'text-sm text-gray-500' }" />
                                {{ trans("Clear all") }}
                            </button>
                        </div>

                        <div class="text-sm text-gray-500">
                            {{ selectedImageCount }} / 3 • {{ totalSizeText }}
                        </div>
                    </div>

                    <input ref="fileInputRef" type="file" class="hidden" accept="image/*" multiple
                        @change="handleFileInputChange" />

                    <div v-if="fileErrors" class="mt-3 text-sm text-red-500">
                        {{ fileErrors }}
                    </div>

                    <div v-if="imagePreviews.length" class="grid gap-3 pt-4 sm:grid-cols-3">
                        <div v-for="(item, index) in imagePreviews" :key="item.file.name + item.file.size"
                            class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-gray-50">
                            <img :src="item.objectURL" :alt="item.file.name" class="h-36 w-full object-cover" />

                            <div class="space-y-1 p-3">
                                <div class="truncate text-sm font-medium text-gray-800">
                                    {{ item.file.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ formatSize(item.file.size) }}
                                </div>
                            </div>

                            <button type="button" @click="removeImage(index)"
                                class="absolute right-2 top-2 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm transition hover:bg-gray-100">
                                <Icon :data="{ icon: 'fal fa-times', class: 'text-xs' }" />
                            </button>
                        </div>
                    </div>


                     <div v-if="modelValue.review_images.length" class="grid gap-3 pt-4 sm:grid-cols-3">
                        <div v-for="(item, index) in modelValue.review_images" :key="item.name + item.size"
                            class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-gray-50">
                            <Image :src="item.thumbnail" :alt="item.name" class="h-36 w-full object-cover" />

                            <div class="space-y-1 p-3">
                                <div class="truncate text-sm font-medium text-gray-800">
                                    {{ item.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ item.size }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>