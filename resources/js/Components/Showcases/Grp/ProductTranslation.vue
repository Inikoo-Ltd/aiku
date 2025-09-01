<script setup lang="ts">
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import TranslationBox from '@/Components/TranslationBox.vue';

const props= defineProps<{
    taxonomy: any
    data: {
        stockImagesRoute: routeType
        uploadImageRoute: routeType
        attachImageRoute: routeType
        deleteImageRoute: routeType
        imagesUploadedRoutes: routeType
        translation_box: {
            title: string
            languages: Record<string, string>
            save_route: routeType
        }
        product: {
            data: {
                id: number
                slug: string
                image_id: number
                code: string
                name: string
                price: string
                description?: string
                state: string
                created_at: string
                updated_at: string
                images: Images[]
                currency_code: string
            }
        }
        stats: {
            amount: number | null
            amount_ly: number | null
            name: string
            percentage: number | null
        }[] | null
        trade_units: {
            brand: {}
            brand_routes: {
                index_brand: routeType
                store_brand: routeType
                update_brand: routeType
                delete_brand: routeType
                attach_brand: routeType
                detach_brand: routeType
            }
            tag_routes: {
                index_tag: routeType
                store_tag: routeType
                update_tag: routeType
                delete_tag: routeType
                attach_tag: routeType
                detach_tag: routeType
            }
            tags: {}[]
            tags_selected_id: number[]
        }[]
    }
}>()

</script>

<template>
    <TranslationBox v-bind="data.translation_box" :master="data.product.data" :needTranslation="data.product.data" />
</template>