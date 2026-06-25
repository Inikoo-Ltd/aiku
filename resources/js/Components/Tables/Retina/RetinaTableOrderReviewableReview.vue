<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref } from "vue"

import Image from "@/Common/Components/Image.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"

import Rating from "primevue/rating"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"

import { faStar as falStar } from "@fal"
import { faStar } from "@fas"
import FormReview from "@/Components/Retina/FormReview.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { router } from "@inertiajs/vue3"

library.add(faStar, falStar)

const props = defineProps<{
    data: {
        rating_labels?: any
        context?: string
    }
    tab?: string
}>()

const locale = inject("locale", retinaLayoutStructure)
const isOpenDialog = ref(false)
const selectedItem = ref<any>(null)
const loadingSave = ref(false)

const openDialog = (item: any) => {
    selectedItem.value = item
    isOpenDialog.value = true
}

const saveReview = async () => {
    const review = selectedItem.value?.review || {}
    const isUpdate = false

    const routeName = isUpdate
        ? "retina.models.review.update"
        : "retina.models.review.store"

    const routeParams = isUpdate ? { review: review.review_id } : { order: review.order_id }

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

        isOpenDialog.value = false
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
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image)="{ item }">
            <div class="flex relative w-8 aspect-square overflow-hidden">
                <Image :src="item.image?.thumbnail" class="w-full h-full object-contain" />
            </div>
        </template>

        <template #cell(asset_name)="{ item }">
            <div class="flex items-center gap-2 text-sm">
                <span v-tooltip="trans('code')" class="px-2 py-1 rounded-md bg-gray-100 text-gray-700 font-medium">
                    {{ item.asset_code }}
                </span>
                <span v-tooltip="trans('name')">
                    {{ item.asset_name }}
                </span>
            </div>
        </template>

        <template #cell(family_name)="{ item }">
            <div class="flex items-center gap-2 text-sm">
                <span v-tooltip="trans('code')" class="px-2 py-1 rounded-md bg-gray-100 text-gray-700 font-medium">
                    {{ item.family_code }}
                </span>
                <span v-tooltip="trans('name')">
                    {{ item.family_name }}
                </span>
            </div>
        </template>

        <template #cell(price)="{ item }">
            <div class="text-right">
                <span v-if="item.price !== null">{{ locale.currencyFormat(item.currency_code || '', item.price) }}</span>
            </div>
        </template>

        <template #cell(review_rating)="{ item }">
            <div class="flex justify-end cursor-pointer rating" @click="openDialog(item)">
                <Rating v-model="item.review_rating" :disabled="true" />
            </div>
        </template>
    </Table>

    <Dialog v-model:visible="isOpenDialog" modal :header="trans('Review')"  :content-style="{ overflow: 'auto' }">
        <FormReview v-if="selectedItem" v-model="selectedItem.review" :type="data.context || ''" :schema="data.rating_labels" />
        <template #footer>
            <div class="flex justify-end gap-5">
                <Button :label="trans('Close')" type="secondary" @click="isOpenDialog = false" />
                <Button :label="trans('Save')" type="save" :loading="loadingSave" @click="saveReview" />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>
