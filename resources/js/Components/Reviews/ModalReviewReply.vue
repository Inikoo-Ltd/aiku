<script setup lang="ts">
import { computed, ref } from "vue"
import Rating from "primevue/rating"
import Textarea from "primevue/textarea"


import type { Image as ImageProxy } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { useFormatTime } from "@/Composables/useFormatTime";
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faPencil, faReply, faTrashAlt } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

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
    schema: SchemaPayload
    replier_type: string
    reviewable_type: string
    reviewable_id: number
    modelValue: {
        status?: "pending" | "approved" | "rejected" | null
        rating?: number | null
        rating_a?: number | null
        rating_b?: number | null
        rating_c?: number | null
        rating_d?: number | null
        rating_e?: number | null
        message?: string | null
        reply?: string | null
        image_thumbnail?: ImageProxy | string | null
        images?: File[] | null
    }
}>()

const emit = defineEmits<{
    (e: "replyAfterUpdate", value: string): void
}>()
const loadingSave = ref(false)
const loadingDelete = ref(false)
const isEditMode = ref(false)
const storeReply = ref("")
const editReplyText = ref("")

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
            ...(props.schema?.shop_reviews ?? []),
            ...(props.schema?.product_reviews ?? []),
            ...(props.schema?.product_category_reviews ?? []),
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

const postReply = async () => {
    try {
        loadingSave.value = true
        await axios({
            method: "post",
            url: route('grp.models.review.reply.store'),
            data: {
                reviewable_type: props.reviewable_type,
                reviewable_id: props.reviewable_id,
                replier_type: props.replier_type,
                body: storeReply.value
            },
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })
        emit('replyAfterUpdate', storeReply.value)
        notify({
            title: "Success",
            text: "Review submitted successfully",
            type: "success",
        })
        storeReply.value = ""
    } catch (errors) {
        console.error(errors)
        notify({
            title: "Error",
            text: "Failed to submit review",
            type: "error",
        })
    } finally {
        loadingSave.value = false
    }
}

const enterEditMode = () => {
    isEditMode.value = true
    editReplyText.value = props.modelValue.existing_reply?.body || ""
}

const cancelEditMode = () => {
    isEditMode.value = false
    editReplyText.value = ""
}

const updateReply = async () => {
    try {
        loadingSave.value = true
        const replyId = props.modelValue.existing_reply?.id
        await axios({
            method: "patch",
            url: route('grp.models.review.reply.update', { reviewReply: replyId }),
            data: {
                body: editReplyText.value
            },
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })
        emit('replyAfterUpdate', storeReply.value)
        notify({
            title: "Success",
            text: "Reply updated successfully",
            type: "success",
        })
        isEditMode.value = false
        editReplyText.value = ""
    } catch (errors) {
        console.error(errors)
        notify({
            title: "Error",
            text: "Failed to update reply",
            type: "error",
        })
    } finally {
        loadingSave.value = false
    }
}

const deleteReply = async () => {
    if (!confirm(trans("Are you sure you want to delete this reply?"))) return

    try {
        loadingDelete.value = true
        const replyId = props.modelValue.existing_reply?.id
        await axios({
            method: "delete",
            url: route('grp.models.review.reply.delete', { reviewReply: replyId }),
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })
        emit('replyAfterUpdate', storeReply.value)
        notify({
            title: "Success",
            text: "Reply deleted successfully",
            type: "success",
        })
    } catch (errors) {
        console.error(errors)
        notify({
            title: "Error",
            text: "Failed to delete reply",
            type: "error",
        })
    } finally {
        loadingDelete.value = false
    }
}
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div
            class="flex flex-col gap-3 rounded border border-gray-200 bg-gray-50 p-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">
                    {{ trans("Review") }}
                </h2>

                <p class="text-sm text-gray-500">
                    {{ trans("Customer feedback detail") }}
                </p>
            </div>

            <div class="rating flex items-center gap-3 rounded-xl border bg-white px-3 py-2">
                <div class="text-xl font-bold text-gray-900 ">
                    {{ averageRating ?? "0.0" }}
                </div>

                <Rating :modelValue="averageRating || 0" readonly :cancel="false" />
            </div>
        </div>

        <!-- Ratings -->
        <div class="rating grid gap-3 md:grid-cols-2">
            <div v-for="item in activeRatings" :key="item.dimension"
                class="flex items-center justify-between rounded border border-gray-200 bg-white p-3">
                <div class="flex items-center gap-3 rating">
                    <div>
                        <div class="text-sm font-medium text-gray-800">
                            {{ item.label }}
                        </div>

                        <div v-if="item.required" class="text-[11px] text-red-500">
                            {{ trans("Required") }}
                        </div>
                    </div>
                </div>

                <Rating :modelValue="props.modelValue?.[item.field]" readonly :cancel="false" />
            </div>
        </div>

        <!-- Customer Comment -->
        <div class="space-y-3">
            <div class="rounded border border-gray-200 bg-white p-3 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold uppercase text-white">
                            C
                        </div>

                        <div>
                            <div class="text-sm font-semibold text-gray-900">
                                {{ modelValue.contact_name }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ useFormatTime(modelValue.created_at) }}
                            </div>
                        </div>
                    </div>

                    <div class="text-xs font-medium text-gray-500">
                        {{ averageRating ? averageRating + ' / 5' : 'No rating' }}
                    </div>
                </div>

                <div class="mt-3 text-sm leading-relaxed whitespace-pre-line text-gray-700">
                    {{ props.modelValue.message || "-" }}
                </div>
            </div>

            <div v-if="props.modelValue.reply_status == 'Yes'" class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
                <div v-if="!isEditMode" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-500 text-xs font-semibold uppercase text-white">
                                S
                            </div>

                            <div>
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ trans("Store") }}
                                </div>
                                <div class="text-xs text-orange-600">
                                    {{ trans("Official Reply") }}
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button @click="enterEditMode" class="text-gray-500 hover:text-gray-700 transition"
                                :title="trans('Edit')">
                                <FontAwesomeIcon :icon="faPencil" />
                            </button>
                            <button @click="deleteReply" :disabled="loadingDelete"
                                class="text-gray-500 hover:text-red-600 transition disabled:opacity-50"
                                :title="trans('Delete')">
                                <FontAwesomeIcon :icon="faTrashAlt" />
                            </button>
                        </div>
                    </div>

                    <div class="text-sm leading-relaxed whitespace-pre-line text-gray-700">
                        {{ props.modelValue?.existing_reply?.body }}
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <div class="text-sm font-medium text-gray-800">
                        {{ trans("Edit Reply") }}
                    </div>

                    <Textarea v-model="editReplyText" rows="4" autoResize class="w-full"
                        :placeholder="trans('Write a professional reply...')" />

                    <div class="flex justify-end gap-2">
                        <Button :label="trans('Cancel')" size="xs" type="secondary" @click="cancelEditMode" />
                        <Button :label="trans('Update Reply')" size="xs" @click="updateReply" :loading="loadingSave"
                            :icon="faPencil" />
                    </div>
                </div>
            </div>




            <div v-else class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-3">
                <div class="mb-2 text-sm font-medium text-gray-800">
                    {{ trans("Reply as Shop") }}
                </div>

                <Textarea v-model="storeReply" rows="4" autoResize class="w-full"
                    :placeholder="trans('Write a professional reply...')" />

                <div class="mt-3 flex justify-end">
                    <Button :label="trans('Send Reply')" size="xs" @click="() => postReply()" :loading="loadingSave"
                        :icon="faReply" />
                </div>
            </div>
        </div>

        <!-- Images -->
        <!--
        <div v-if="props.modelValue.image_thumbnail" class="space-y-2">
            <div class="text-sm font-medium text-gray-800">
                {{ trans("Images") }}
            </div>

            <div class="flex flex-wrap gap-2">
                <img
                    v-for="(img, i) in (props.modelValue.image_thumbnail as any[])"
                    :key="i"
                    :src="img"
                    class="h-20 w-20 rounded-xl border object-cover"
                />
            </div>
        </div>
        -->
    </div>
</template>

<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>