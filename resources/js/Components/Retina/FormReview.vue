<script setup lang="ts">
import Tag from "@/Components/Tag.vue"
import { ref } from "vue"
import type { Image as ImageProxy } from "@/types/Image"
import { Textarea } from "primevue"
import ImagePrime from "primevue/image"
import Select from "primevue/select"
import { trans } from "laravel-vue-i18n"
import { useForm } from "@inertiajs/vue3"

const props = defineProps<{
    product_category_id: number
    review?: {
        id: number
        customer_id?: number | null
        contact_name?: string | null
        customer_name?: string | null
        status?: "pending" | "approved" | "rejected" | null
        rating?: number | null
        rating_a?: number | null
        rating_b?: number | null
        rating_c?: number | null
        rating_d?: number | null
        rating_e?: number | null
        message?: string | null
        created_at?: string | null
        image_thumbnail?: ImageProxy | string | null
        image_thumbnails?: Array<ImageProxy | string> | null
        image_gallery?: Array<ImageProxy | string> | null
        existing_reply?: {
            id: number
            body?: string | null
            is_public?: boolean
            status?: "pending" | "approved" | "rejected" | null
        } | null
    }
}>()


const ratingByDimension = ref<Record<string, number>>({})
const mainRating = ref(5)
const message = ref("")
const errors = ref<Record<string, string[]>>({})
const imageFiles = ref<File[]>([])
const selectedCustomerId = ref<number | null>(null)
const selectedStatus = ref<"pending" | "approved" | "rejected">("pending")
const isLoadingCustomers = ref(false)

const form = useForm({
    message : ""
    imageFiles : null
})

</script>

<template>
    <div>
            <div class="max-h-[78vh] space-y-3 overflow-y-auto p-1 pr-2">
                <h2 class="text-center text-xl font-bold">{{ trans('Review')}}</h2>
                
            </div>
    </div>
</template>
