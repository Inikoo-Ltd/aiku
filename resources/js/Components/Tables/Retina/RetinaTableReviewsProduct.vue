<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref } from "vue"

import Image from "@/Common/Components/Image.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"

import Rating from "primevue/rating"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"

import {
    faConciergeBell,
    faGarage,
    faExclamationTriangle,
    faPencil,
    faSearch,
    faThLarge,
    faListUl,
    faStar as falStar,
    faTrashAlt,
    faExclamationCircle
} from "@fal"

import { faStar, faFilter } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck } from "@far"
import FormReview from "@/Components/Retina/FormReview.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { router } from "@inertiajs/vue3"

library.add(
    fadExclamationTriangle,
    faConciergeBell,
    faGarage,
    faExclamationTriangle,
    faPencil,
    faSearch,
    faThLarge,
    faListUl,
    faStar,
    faFilter,
    falStar,
    faTrashAlt,
    faCheck,
    faExclamationCircle
)

const props = defineProps<{
    data: {}
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

const saveProductReview = async () => {
    const isUpdate = !!selectedItem.value?.product_review_rating

    const routeName = isUpdate
        ? "retina.models.review.update"
        : "retina.models.review.store"

    const routeParams = isUpdate
        ? {
              review:
                  selectedItem.value?.reviews?.product?.review_id,
          }
        : undefined

    const payload = {
        ...selectedItem.value?.reviews?.product,
      /*   ...selectedItem.value?.reviews?.product?.payload, */
    }

    const formData = new FormData()

    Object.entries(payload || {}).forEach(([key, value]) => {
        if (key === "images" && Array.isArray(value)) {
            value.forEach((file: File) => {
                formData.append("images[]", file)
            })
        } else {
            formData.append(key, value as any)
        }
    })

    try {
        loadingSave.value = true

        await axios({
            method: isUpdate ? "patch" : "post",
            url: route(routeName, routeParams),
            data: formData,
            headers: {
                "Content-Type": "multipart/form-data",
            },
        })

        isOpenDialog.value = false

        router.reload({only: ['pageHead', 'reviews']})
        notify({
            title: "Success",
            text: "Review submitted successfully",
            type: "success",
        })
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
                <span v-tooltip="'code'" class="px-2 py-1 rounded-md bg-gray-100 text-gray-700 font-medium">
                    {{ item.asset_code }}
                </span>

                <span v-tooltip="'name'">
                    {{ item.asset_name }}
                </span>
            </div>
        </template>

        <template #cell(price)="{ item }">
            <div class="text-right">
                {{ locale.currencyFormat(item.currency_code || '', item.price) }}
            </div>
        </template>

        <template #cell(product_review_rating)="{ item }">
            <div class="flex justify-end cursor-pointer rating" @click="openDialog(item)">
                <pre>{{ item.reviews.product }}</pre>
                <Rating v-model="item.product_review_rating" :disabled="true" />
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex gap-2"></div>
        </template>
    </Table>

    <Dialog v-model:visible="isOpenDialog" modal header="Product Review" :style="{ width: '550px' }" :content-style="{ overflow: 'auto' }">
       <pre>{{ selectedItem.reviews.product }}</pre> 
        <FormReview v-model="selectedItem.reviews.product" :schema="data.rating_labels.product_reviews" />
        <template #footer>
            <div class="flex justify-end gap-5">
                <Button label="Close" type="secondary" @click="isOpenDialog = false" />
                <Button label="Save" type="save" :loading="loadingSave" @click="saveProductReview" />
            </div>
        </template>
    </Dialog>
</template>


<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>