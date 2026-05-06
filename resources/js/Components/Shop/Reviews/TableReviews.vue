<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import ReviewStatsPanel from "@/Components/Shop/Reviews/ReviewStatsPanel.vue"
import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalCreateCategoryReviews from "@/Components/Reviews/ModalCreateCategoryReviews.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { trans } from "laravel-vue-i18n"

type ReviewTablePayload = TableTS & {
    stats?: {
        total?: number
        average_rating?: number
        verified?: number
        like_count?: number
        status_approved?: number
        status_pending?: number
        status_rejected?: number
        number_reviews_rating_1?: number
        number_reviews_rating_2?: number
        number_reviews_rating_3?: number
        number_reviews_rating_4?: number
        number_reviews_rating_5?: number
    }
}

type RatingLabel = {
    dimension: string
    label: string
    is_required?: boolean
    weight?: number
}

const props = defineProps<{
    data: ReviewTablePayload
    tab?: string
    product_category_id?: number
    customers?: {
        data: Array<{
            customer_id: number
            label: string
            contact_name?: string | null
            username?: string
            email?: string | null
        }>
        meta?: {
            current_page?: number
            per_page?: number
            next_page?: number | null
            has_more?: boolean
        }
    }
    rating_labels?: RatingLabel[]
}>()

const renderStars = (rating: number): string => {
    const value = Number.isFinite(rating) ? Math.max(0, Math.min(5, rating)) : 0
    return "★".repeat(value)
}
</script>

<template>
    <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-10">
        <div class="xl:col-span-7">
            <Table :resource="data" :name="tab">
                <template #cell(image_thumbnails)="{ item }">
                    <div class="flex items-center gap-1">
                        <template v-if="Array.isArray(item.image_thumbnails) && item.image_thumbnails.length">
                            <Image
                                v-for="(thumbnail, index) in item.image_thumbnails.slice(0, 3)"
                                :key="`${item.id}-image-${index}`"
                                :src="thumbnail"
                                class="h-8 w-8 overflow-hidden rounded object-cover"
                            />
                            <span
                                v-if="item.image_thumbnails.length > 3"
                                class="text-xs text-gray-500"
                            >
                                +{{ item.image_thumbnails.length - 3 }}
                            </span>
                        </template>
                        <div v-else class="h-8 w-8 rounded border border-gray-200" />
                    </div>
                </template>
                <template #cell(rating)="{ item }">
                    <span class="text-amber-500">{{ renderStars(item.rating) }}</span>
                </template>
                <template #cell(action)="{ item }">
                    <div class="flex items-center justify-end gap-1">
                        <ModalCreateCategoryReviews
                            :hideDefaultButton="true"
                            mode="detail"
                            :product_category_id="product_category_id ?? 0"
                            :review="item"
                            :customers="customers"
                            :rating_labels="rating_labels"
                        >
                            <template #trigger="{ openModal }">
                                <Button type="tertiary" icon="far fa-eye" size="xs" @click="openModal" />
                            </template>
                        </ModalCreateCategoryReviews>

                        <ModalCreateCategoryReviews
                            :hideDefaultButton="true"
                            mode="update"
                            :product_category_id="product_category_id ?? 0"
                            :review="item"
                            :customers="customers"
                            :rating_labels="rating_labels"
                        >
                            <template #trigger="{ openModal }">
                                <Button :style="'edit'" size="xs" @click="openModal" />
                            </template>
                        </ModalCreateCategoryReviews>

                        <ModalConfirmationDelete
                            :routeDelete="item.delete_route"
                            :title="trans('Are you sure you want to delete this review?')"
                            isFullLoading
                        >
                            <template #default="{ changeModel }">
                                <Button icon="fal fa-trash-alt" type="negative" size="xs" @click="changeModel" />
                            </template>
                        </ModalConfirmationDelete>
                    </div>
                </template>
            </Table>
        </div>

        <div class="xl:col-span-3">
            <ReviewStatsPanel :stats="data.stats" />
        </div>
    </div>
</template>
