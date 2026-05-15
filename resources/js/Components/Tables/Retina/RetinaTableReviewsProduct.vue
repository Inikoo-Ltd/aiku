<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref } from "vue"

import Image from "@/Common/Components/Image.vue"
import ModalCreateCategoryReviews from "@/Components/Reviews/ModalCreateCategoryReviews.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"

import Rating from "primevue/rating"
import Dialog from "primevue/dialog"
import Button from "primevue/button"

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

defineProps<{
    data: {}
    tab?: string
}>()

const locale = inject("locale", retinaLayoutStructure)
const isOpenDialog = ref(false)
const selectedItem = ref<any>(null)

const openDialog = (item: any) => {
    console.log(item)
    selectedItem.value = item
    isOpenDialog.value = true
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
                <span
                    v-tooltip="'code'"
                    class="px-2 py-1 rounded-md bg-gray-100 text-gray-700 font-medium"
                >
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
            <div
                class="flex justify-end cursor-pointer"
                @click="openDialog(item)"
            >
                <Rating
                    v-model="item.product_review_rating"
                    :disabled="true"
                />
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex gap-2"></div>
        </template>
    </Table>

    <Dialog
        v-model:visible="isOpenDialog"
        modal
        header="Product Review"
        :style="{ width: '500px' }"
    >
        <pre>{{ selectedItem.reviews }}</pre>

        <template #footer>
            <Button
                label="Close"
                severity="secondary"
                @click="isOpenDialog = false"
            />
        </template>
    </Dialog>
</template>