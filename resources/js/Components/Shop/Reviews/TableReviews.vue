<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import ReviewStatsPanel from "@/Components/Shop/Reviews/ReviewStatsPanel.vue"
import Image from "@/Common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Tag from "@/Components/Tag.vue"
import { router } from "@inertiajs/vue3"
import ReviewReply from "@/Components/Reviews/ReviewReply.vue"
import { trans } from "laravel-vue-i18n"
import { faPencil, faReply, faEye } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Dialog from "primevue/dialog"
import { ref } from "vue"
import { Rating } from "primevue"


library.add(
    faPencil,
    faReply,
    faEye
)

type ReviewTablePayload = TableTS & {
    stats?: {
        total?: number
        average_rating?: number
        status_approved?: number
        status_pending?: number
        status_rejected?: number
        number_reviews_rating_1?: number
        number_reviews_rating_2?: number
        number_reviews_rating_3?: number
        number_reviews_rating_4?: number
        number_reviews_rating_5?: number
        category_ratings?: Array<{
            dimension: string
            label: string
            average: number
        }>
    }
}

type RatingLabel = {
    dimension: string
    label: string
    is_required?: boolean
    weight?: number
}

const props = defineProps<{
    data: {
        data: ReviewTablePayload
        reviewable_type?: "ProductCategory" | "Product" | "Shop"
        rating_labels?: RatingLabel[]
        replier_type:String
    }
    tab?: string
}>()

const isDialogVisible = ref(false)
const selectedItem = ref<any | null>(null)

const openModal = (item: any) => {
    console.log(item)
    selectedItem.value = item
    isDialogVisible.value = true
}

const closeModal = () => {
    isDialogVisible.value = false
    selectedItem.value = null
}


const aftreReply = () => {
    isDialogVisible.value = false
    router.reload({only: ['pageHead', 'reviews']})
}


</script>

<template>
    <div class="p-4">
        <ReviewStatsPanel :stats="data.stats" :rating-labels="rating_labels" />
    </div>

    <Table :resource="data.data" :name="tab">
        <template #cell(image_thumbnails)="{ item }">
            <div class="flex items-center gap-1">
                <template v-if="Array.isArray(item.image_thumbnails) && item.image_thumbnails.length">
                    <Image v-for="(thumbnail, index) in item.image_thumbnails.slice(0, 3)"
                        :key="`${item.id}-image-${index}`" :src="thumbnail"
                        class="h-8 w-8 overflow-hidden rounded object-cover" />
                </template>
                <div v-else class="h-8 w-8 rounded border border-gray-200" />
            </div>
        </template>
        <template #cell(rating)="{ item }">
            <div class="rating">
                <Rating :modelValue="item.rating" readonly />
            </div>
        </template>
        <template #cell(reply_status)="{ item }">
            <Tag :theme="item.has_reply ? 3 : 99" :label="item.has_reply ? trans('Yes') : trans('No')" />
        </template>
        <template #cell(action)="{ item }">
            <div class="flex items-center justify-end gap-1">
                <Button type="tertiary" :icon="faEye" size="xs" @click="() => openModal(item)" />
            </div>
        </template>
    </Table>


    <Dialog v-model:visible="isDialogVisible" modal header="Review Detail" :style="{ width: '40rem' }"
        :breakpoints="{ '960px': '75vw', '641px': '90vw' }" @hide="closeModal">
        <div v-if="selectedItem" class="space-y-3">
            <ReviewReply
                :modelValue="selectedItem"
                :schema="data.rating_labels"
                :replier_type="data.replier_type"
                :reviewable_id="selectedItem.id"
                :reviewable_type="data.reviewable_type"
                @reply-after-update="aftreReply"
            />
        </div>
    </Dialog>

</template>


<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
    color: #f59e0b !important;
}
</style>