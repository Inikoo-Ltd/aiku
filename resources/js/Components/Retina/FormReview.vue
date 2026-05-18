<script setup lang="ts">
import { computed, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Rating from "primevue/rating"
import Textarea from "primevue/textarea"
import type { Image as ImageProxy } from "@/types/Image"
import { trans } from "laravel-vue-i18n"

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
    modelValue: {
        status?: "pending" | "approved" | "rejected" | null
        rating?: number | null
        rating_a?: number | null
        rating_b?: number | null
        rating_c?: number | null
        rating_d?: number | null
        rating_e?: number | null
        message?: string | null
        image_thumbnail?: ImageProxy | string | null
    }
}>()

const emit = defineEmits<{
    (event: "update:modelValue", value: typeof props.modelValue): void
}>()

const form = useForm({
    message: props.modelValue.message ?? "",
    rating_a: props.modelValue.rating_a ?? null,
    rating_b: props.modelValue.rating_b ?? null,
    rating_c: props.modelValue.rating_c ?? null,
    rating_d: props.modelValue.rating_d ?? null,
    rating_e: props.modelValue.rating_e ?? null,
    rating: props.modelValue.rating ?? null,
    status: props.modelValue.status ?? 'approved',
})

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
            ...(props.schema.shop_reviews ?? []),
            ...(props.schema.product_reviews ?? []),
            ...(props.schema.product_category_reviews ?? []),
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
})

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
    <div class="space-y-4">
        <div
            class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 to-white p-4 lg:flex-row lg:items-center lg:justify-between"
        >
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

            <div
                class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3"
            >
                <div>
                    <div
                        class="text-2xl font-bold leading-none text-gray-900"
                    >
                        {{ averageRating ?? "0.0" }}
                    </div>

                    <div class="mt-1 text-[11px] text-gray-500">
                        {{ trans("Average Rating") }}
                    </div>
                </div>

                <Rating
                    :modelValue="averageRating || 0"
                    readonly
                    :cancel="false"
                />
            </div>
        </div>

        <div class="space-y-3">
            <template v-if="activeRatings.length">
                <div
                    v-for="item in activeRatings"
                    :key="item.dimension"
                    class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 transition-all duration-200 hover:border-gray-200 hover:bg-white sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-[11px] font-bold uppercase text-white"
                        >
                            {{ item.dimension }}
                        </div>

                        <div>
                            <div
                                class="text-sm font-medium leading-none text-gray-800"
                            >
                                {{ item.label }}
                            </div>

                            <div
                                v-if="item.required"
                                class="mt-1 text-[11px] text-red-500"
                            >
                                {{ trans("Required") }}
                            </div>
                        </div>
                    </div>

                    <Rating
                        v-model="form[item.field]"
                        :cancel="false"
                    />
                </div>
            </template>

            <div
                v-else
                class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500"
            >
                {{ trans( "No review dimensions available." )}}
            </div>

            <div class="space-y-2 pt-1">
                <label class="text-sm font-medium text-gray-800">
                    {{ trans("Your Review") }}
                </label>

                <Textarea
                    v-model="form.message"
                    rows="4"
                    :autoResize="true"
                    :placeholder="trans('Tell people what you liked or disliked...')"
                    class="w-full rounded-xl"
                />
            </div>
        </div>
    </div>
</template>