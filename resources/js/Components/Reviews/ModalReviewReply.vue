<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { Textarea } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import axios from "axios"

const props = defineProps<{
    review: {
        id: number
        reviewable_type: "product_reviews" | "product_category_reviews" | "shop_reviews"
        contact_name?: string | null
        customer_name?: string | null
        rating?: number | null
        message?: string | null
        created_at?: string | null
    }
    hideDefaultButton?: boolean
}>()

const isOpenModal = ref(false)
const body = ref("")
const isSubmitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const modalTitle = computed(() => trans("Reply Review"))
const customerName = computed(() => props.review.contact_name ?? props.review.customer_name ?? trans("Customer"))
const ratingValue = computed(() => {
    const value = Number(props.review.rating ?? 0)
    if (!Number.isFinite(value) || value <= 0) {
        return 0
    }

    return Math.max(0, Math.min(5, Math.round(value)))
})
const ratingStars = computed(() => "★".repeat(ratingValue.value))
const ratingEmptyStars = computed(() => "☆".repeat(Math.max(0, 5 - ratingValue.value)))
const reviewMessage = computed(() => {
    const value = props.review.message?.trim()
    return value && value.length > 0 ? value : "-"
})
const reviewedAt = computed(() => {
    if (!props.review.created_at) {
        return "-"
    }

    const date = new Date(props.review.created_at)
    if (Number.isNaN(date.getTime())) {
        return "-"
    }

    return date.toLocaleString()
})
const canSubmit = computed(() => body.value.trim().length > 0 && !isSubmitting.value)

const openModal = (): void => {
    errors.value = {}
    body.value = ""
    isOpenModal.value = true
}

const closeModal = (): void => {
    isOpenModal.value = false
    errors.value = {}
}

const submitReply = async (): Promise<void> => {
    if (isSubmitting.value) {
        return
    }

    if (body.value.trim().length === 0) {
        errors.value = {
            body: [trans("Reply is required")],
        }
        return
    }

    isSubmitting.value = true
    errors.value = {}

    try {
        await axios.post(route("grp.models.review.reply.store"), {
            reviewable_type: props.review.reviewable_type,
            reviewable_id: props.review.id,
            body: body.value,
            is_public: true,
            status: "approved",
        })

        notify({
            title: trans("Success"),
            text: trans("Reply created successfully"),
            type: "success",
        })

        closeModal()
        router.reload()
    } catch (error: any) {
        errors.value = error?.response?.data?.errors ?? {}
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to create reply"),
            type: "error",
        })
    } finally {
        isSubmitting.value = false
    }
}

const autoGenerateReply = (): void => {
    const rating = Number(props.review.rating ?? 0)
    const cleanedMessage = props.review.message?.trim()

    if (rating >= 4) {
        body.value = `${trans("Hi")} ${customerName.value}, ${trans("thank you for your positive review and high rating. We are happy to know you had a great experience with us.")}`
    } else if (rating >= 2) {
        body.value = `${trans("Hi")} ${customerName.value}, ${trans("thank you for your review. We appreciate your feedback and will keep improving to provide a better experience.")}`
    } else {
        body.value = `${trans("Hi")} ${customerName.value}, ${trans("thank you for sharing your feedback. We are sorry your experience did not meet expectations. Our team is reviewing this and will improve our service.")}`
    }

    if (cleanedMessage) {
        body.value = `${body.value} ${trans("We have noted your comment")}: "${cleanedMessage}".`
    }
}
</script>

<template>
    <div>
        <slot name="trigger" :openModal="openModal">
            <Button
                v-if="!hideDefaultButton"
                type="tertiary"
                icon="fal fa-reply"
                size="xs"
                @click="openModal"
            />
        </slot>

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="space-y-4 p-1">
                <div class="text-xl font-semibold text-gray-800">{{ modalTitle }}</div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="text-lg font-semibold text-gray-900">{{ customerName }}</div>
                        <div class="text-base leading-none text-amber-400">
                            <span>{{ ratingStars }}</span>
                            <span class="text-gray-300">{{ ratingEmptyStars }}</span>
                        </div>
                    </div>
                    <div class="mt-3 text-lg font-semibold leading-7 text-gray-900">
                        {{ reviewMessage }}
                    </div>
                    <div class="mt-2 text-right text-xs text-gray-500">
                        {{ ` ${reviewedAt}` }}
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Reply") }}</label>
                    <Textarea
                        v-model="body"
                        rows="6"
                        class="w-full"
                        :placeholder="trans('Write your reply')"
                    />
                    <div v-if="errors.body?.[0]" class="text-sm text-red-500">{{ errors.body[0] }}</div>
                </div>

                <div class="flex justify-end gap-3">
                    <Button
                        type="tertiary"
                        :label="trans('Auto generate reply')"
                        icon="fal fa-wand-magic-sparkles"
                        :disabled="isSubmitting"
                        @click="autoGenerateReply"
                    />
                    <Button type="cancel" @click="closeModal" />
                    <Button
                        :label="trans('Post reply')"
                        :isLoading="isSubmitting"
                        :disabled="!canSubmit"
                        icon="fal fa-paper-plane"
                        @click="submitReply"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
