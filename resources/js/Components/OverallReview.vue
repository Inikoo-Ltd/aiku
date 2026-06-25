<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { trans } from "laravel-vue-i18n"
import { faStar as falStar } from "@fal"
import { faStar } from "@fas"
import FormReview from "@/Components/Retina/FormReview.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faStar, falStar)

const props = defineProps<{
    data: {
        rating_labels?: any
        context?: string
    }
}>()

const locale = inject("locale", retinaLayoutStructure)
const loadingSave = ref(false)



const saveReview = async () => {
    const review = props.data || {}
    const isUpdate = !!review.review_id

    const routeName = isUpdate
        ? "retina.models.review.update"
        : "retina.models.review.store"

    const routeParams = isUpdate ? { review: review.review_id } : undefined

    const payload: Record<string, any> = {
        reviewable_type: review.reviewable_type,
        reviewable_id: review.reviewable_id,
        order_id: review.order_id,
        rating: review.rating,
        rating_a: review.rating_a,
        rating_b: review.rating_b,
        rating_c: review.rating_c,
        rating_d: review.rating_d,
        rating_e: review.rating_e,
        message: review.message,
        is_public: review.is_public,
        images: review.images || [],
    }

    const formData = new FormData()

    Object.entries(payload).forEach(([key, value]) => {
        if (key === "images" && Array.isArray(value)) {
            value.forEach((file: File) => formData.append("images[]", file))
            return
        }

        if (value === null || value === undefined) {
            return
        }

        formData.append(key, value as any)
    })

    try {
        loadingSave.value = true

        await axios({
            method: isUpdate ? "patch" : "post",
            url: route(routeName, routeParams),
            data: formData,
            headers: { "Content-Type": "multipart/form-data" },
        })

        router.reload({ only: ["pageHead", props.tab as string] })
        notify({
            title: trans("Success"),
            text: trans("Review submitted successfully"),
            type: "success",
        })
    } catch (errors) {
        console.error(errors)
        notify({
            title: trans("Error"),
            text: trans("Failed to submit review"),
            type: "error",
        })
    } finally {
        loadingSave.value = false
    }
}

</script>

<template>
    <div>
        <FormReview v-model="props.data" :type="data.context || ''" :schema="data.rating_labels" />
        <div class="border-t mt-3 py-3 gap-4 border-gray-200 flex justify-end">
            <Button type="save" @click="saveReview"></Button>
        </div>
    </div>

</template>

<style scoped></style>
