<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref, reactive, watch } from "vue"
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
        review_id?: number | null
        order_id?: number
        reviewable_id?: number
        scope?: string
        rating?: number | null
        rating_a?: number | null
        rating_b?: number | null
        rating_c?: number | null
        rating_d?: number | null
        rating_e?: number | null
        message?: string | null
        is_public?: boolean | null
        images?: File[] | null
    }
    tab?: string
    review_settings : any
}>()


const loadingSave = ref(false)

const reviewData = ref<any>({ ...props.data })

watch(() => props.data, (newData) => {
    reviewData.value = { ...newData }
})

const saveReview = async () => {
    const review = reviewData.value
    const isUpdate = false
    const routeName = isUpdate
        ? "retina.models.review.update"
        : "retina.models.review.store"
    const routeParams: Record<string, any> = isUpdate
        ? { review: review.review_id }
        : { order: review.order_id }
    const payload: Record<string, any> = {
        scope: review.scope,
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

        router.reload()
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
    <div class="border rounded-lg p-4 border-gray-20">
        <div class="text-lg font-bold mb-4 ml-2 border-b pb-3 border-gray-200">{{trans('Overall review of your experience')}}</div>
        <FormReview v-model="reviewData" :review_settings :type="data.context || ''" :schema="data.rating_labels"  :showAverageReview="false"  :disabled="reviewData?.review_id ? true : false"/>
        <div  v-if="!reviewData?.review_id " class="border-t mt-3 pt-3 gap-4 border-gray-200 flex justify-end">
            <Button type="save" @click="saveReview"></Button>
        </div>
    </div>
</template>

<style scoped></style>
