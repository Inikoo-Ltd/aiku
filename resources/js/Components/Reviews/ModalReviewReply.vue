<script setup lang="ts">
import { computed } from "vue"
import Rating from "primevue/rating"
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
        images?: File[] | null
    }
}>()

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
        const dimension = String(item.dimension ?? "").toLowerCase()
        const field = ratingKeyMap[dimension as keyof typeof ratingKeyMap]

        if (!field || grouped.has(dimension)) return

        grouped.set(dimension, {
            dimension,
            field,
            label: item.label?.trim() || `Rating ${dimension.toUpperCase()}`,
            required: item.is_required ?? false,
        })
    })

    return ["a", "b", "c", "d", "e"]
        .map((key) => grouped.get(key))
        .filter(Boolean)
})

const averageRating = computed(() => {
    const values = activeRatings.value
        .map((item: any) => Number(props.modelValue?.[item.field as any]))
        .filter((v) => !Number.isNaN(v) && v > 0)

    if (!values.length) return null

    const total = values.reduce((a, b) => a + b, 0)
    return Number((total / values.length).toFixed(1))
})

const formatSize = (bytes: number) => {
    if (!bytes) return "0 B"
    const k = 1024
    const sizes = ["B", "KB", "MB", "GB"]
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`
}
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ trans("Review") }}
                </h2>
                <p class="text-sm text-gray-500">
                    {{ trans("Customer feedback detail") }}
                </p>
            </div>

            <div class="flex items-center gap-3 rounded-xl border bg-white px-4 py-3">
                <div class="text-2xl font-bold text-gray-900">
                    {{ averageRating ?? "0.0" }}
                </div>

                <Rating :modelValue="averageRating || 0" readonly :cancel="false" />
            </div>
        </div>

        <!-- Ratings -->
        <div class="space-y-3">
            <div
                v-for="item in activeRatings"
                :key="item.dimension"
                class="flex items-center justify-between rounded-xl border bg-white p-3"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-white">
                        {{ item.dimension }}
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-800">
                            {{ item.label }}
                        </div>
                        <div v-if="item.required" class="text-xs text-red-500">
                            {{ trans("Required") }}
                        </div>
                    </div>
                </div>

                <Rating
                    :modelValue="props.modelValue?.[item.field]"
                    readonly
                    :cancel="false"
                />
            </div>
        </div>

        <!-- Message -->
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-800">
                {{ trans("Review") }}
            </div>

            <div class="rounded-xl border bg-white p-3 text-sm text-gray-700">
                {{ props.modelValue.message || "-" }}
            </div>
        </div>

        <!-- Images (view only) -->
        <!-- <div v-if="props.modelValue.image_thumbnail" class="space-y-2">
            <div class="text-sm font-medium text-gray-800">
                {{ trans("Images") }}
            </div>

            <div class="flex gap-2">
                <img
                    v-for="(img, i) in (props.modelValue.image_thumbnail as any[])"
                    :key="i"
                    :src="img"
                    class="h-20 w-20 rounded object-cover border"
                />
            </div>
        </div> -->
    </div>
</template>