<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import Image from "@/Common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { router, Link } from "@inertiajs/vue3"
import ReviewReply from "@/Components/Reviews/ReviewReply.vue"
import { trans } from "laravel-vue-i18n"
import { faPencil, faReply, faCheck, faReplyAll, faArrowUp, faArrowDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Dialog from "primevue/dialog"
import { ref } from "vue"
import { Rating } from "primevue"

library.add(
    faPencil,
    faReply,
    faReplyAll,
    faCheck,
    faArrowUp,
    faArrowDown,
)

type RatingLabel = {
    dimension: string
    label: string
    is_required?: boolean
    weight?: number
}

const props = defineProps<{
    data: {
        data: TableTS
        reviewable_type?: "ProductCategory" | "Product" | "Shop"
        rating_labels?: RatingLabel[]
        replier_type:String
    }
    tab?: string
}>()

const isDialogVisible = ref(false)
const selectedItem = ref<any | null>(null)

const openModal = (item: any) => {
    selectedItem.value = item
    isDialogVisible.value = true
}

const closeModal = () => {
    isDialogVisible.value = false
    selectedItem.value = null
}

const aftreReply = () => {
    isDialogVisible.value = false
    router.reload({preserveScroll: true})
}

const approvingId = ref<number | null>(null)

const approveReview = (item: any) => {
    if (!item?.update_route) {
        return
    }

    approvingId.value = item.id

    router.patch(
        route(item.update_route.name, item.update_route.parameters),
        { status: 'approved' },
        {
            preserveScroll: true,
            only: ['pageHead', 'reviews'],
            onFinish: () => {
                approvingId.value = null
            },
        }
    )
}
</script>

<template>
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

        <template #cell(customer_name)="{ item }">
            <Link
                v-if="item.customer_route"
                :href="route(item.customer_route.name, item.customer_route.parameters)"
                class="primaryLink"
            >
                {{ item.customer_name }}
            </Link>
            <span v-else>{{ item.customer_name }}</span>
        </template>

        <template #cell(message)="{ item }">
            <div class="space-y-1">
                <div class="border-l-2 border-sky-600 pl-3 text-sm italic text-gray-500">
                    <span class="font-medium not-italic text-sky-600">{{ trans('Message') }}:</span>
                    {{ item.message }}
                </div>
                <div
                    v-if="item.has_reply && item.existing_reply?.body"
                    class="border-l-2 border-gray-200 pl-3 text-sm italic text-gray-500"
                >
                    <span class="font-medium not-italic text-gray-400">{{ trans('Reply') }}:</span>
                    {{ item.existing_reply.body }}
                </div>
            </div>
        </template>

        <template #cell(rating)="{ item }">
            <div class="rating">
                <Rating :modelValue="item.rating" readonly />
            </div>
        </template>

        <template #cell(likes)="{ item }">
            <div class="flex items-center justify-end gap-3">
                <span class="flex items-center gap-1">
                    {{ item.likes }}
                    <FontAwesomeIcon :icon="faArrowUp" class="text-green-500" fixed-width />
                </span>
                <span class="flex items-center gap-1">
                    {{ item.dislikes }}
                    <FontAwesomeIcon :icon="faArrowDown" class="text-red-500" fixed-width />
                </span>
            </div>
        </template>

        <template #cell(action)="{ item }">
            <div class="flex items-center justify-end gap-1">
                <Button
                    v-if="item.is_public"
                    type="positive"
                    :icon="faCheck"
                    size="xs"
                    :loading="approvingId === item.id"
                    v-tooltip="trans('Approve')"
                    @click="() => approveReview(item)"
                />
                <Button type="tertiary" :icon="faReplyAll" size="xs" v-tooltip="trans('Reply')" @click="() => openModal(item)" />
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
