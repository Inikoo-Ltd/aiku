<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue"
import { useLocaleStore } from '@/Stores/locale'
import { faCircle, faTrash } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from '@kyvg/vue3-notification'
import Image from "@/Components/Image.vue"
import { Tab, TabGroup, TabList, TabPanel, TabPanels, } from '@headlessui/vue'
import { ref } from 'vue'
import EmptyState from "@/Components/Utils/EmptyState.vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"

import axios from "axios"
import { router } from '@inertiajs/vue3'
import { Image as ImageTS } from "@/types/Image"
import { routeType } from "@/types/route"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import ProductContentsIris from "@/Components/CMS/Webpage/Product1/ProductContentIris.vue"
library.add(faCircle, faTrash)

const props = defineProps<{
    data: {
        imagesUploadedRoutes: routeType
        stockImagesRoute: routeType
        deleteImageRoute: routeType
        attachImageRoute: routeType
        uploadImageRoute: routeType
        product: {
            data: {
                id: number,
                slug: string,
                image_id: number | null,
                code: string,
                name: string,
                price: string,
                currency_code: string,
                description: string | null,
                state: string,
                created_at: string,
                updated_at: string,
                images: ImageTS
                image_thumbnail: string | null
                description_title: string | null,
                description_extra: string | null
            }
        }
    }
}>()


const locale = useLocaleStore()
const openGallery = ref(false)
const selectedImage = ref(0)


// const stats = [
//     { name: '2024', stat: '71,897', previousStat: '70,946', change: '12%', changeType: 'increase' },
//     { name: '2023', stat: '58.16%', previousStat: '56.14%', change: '2.02%', changeType: 'increase' },
//     { name: '2022', stat: '24.57%', previousStat: '28.62%', change: '4.05%', changeType: 'decrease' },
//     { name: '2021', stat: '71,897', previousStat: '70,946', change: '12%', changeType: 'increase' },
//     { name: '2020', stat: '58.16%', previousStat: '56.14%', change: '2.02%', changeType: 'increase' },
//     { name: '2019', stat: '24.57%', previousStat: '28.62%', change: '4.05%', changeType: 'decrease' },
// ]

const product = ref({
    images: props.data.product.data.images,
})

// const OnUploadImages = (e) => {
//     product.value.images.push(...e.data)
//     openGallery.value = false
// }

// const OnPickImages = (e) => {
//     product.value.images.push(e)
//     openGallery.value = false
// }

// const deleteImage = async (data, index) => {
//     console.log(data)

//     try {
//         // router.delete(route(props.data.deleteImageRoute.name, {
//         //     ...props.data.deleteImageRoute.parameters, media: data.id
//         // }))

//         if (selectedImage.value == index) selectedImage.value = 0
//         product.value.images.splice(index, 1)
//     } catch (error: any) {
//         console.log('error', error)
//         notify({
//             title: 'Failed',
//             text: 'cannot show stock images',
//             type: 'error'
//         })
//     }
// }


// function changeSelectedImage(index) {
//     selectedImage.value = index
// }

console.log(props)
</script>


<template>
    <div class="flex flex-col md:grid md:grid-cols-4 gap-x-4 gap-y-4 p-4">
        <div class="md:p-5 space-y-5 md:col-span-2">
            <div class="h-auto w-full aspect-square rounded-lg shadow">
                <ImageProducts v-if="data.product.data.images?.length" :images="data.product.data.images">
                    <template #image-thumbnail="{ image, index }">
                        <div class="aspect-square w-full overflow-hidden group relative">
                            <Image :src="image.thumbnail" :alt="`Thumbnail ${index + 1}`"
                                class="block w-full h-full object-cover rounded-md border" />
                        </div>
                    </template>
                </ImageProducts>
            </div>
        </div>

        <!-- Product Detail -->
        <section aria-labelledby="summary-heading"
            class="col-span-2 xborder xborder-gray-200 rounded-lg px-4 py-6 sm:p-4 lg:mt-0 lg:p-5">

            <dl class="mt-2 space-y-6">
                <div class="flex flex-col">
                    <dt class="text-sm text-gray-500">{{ trans("Name") }}</dt>
                    <dd class="font-bold text-xl">{{ data?.product?.data?.name ?? '-' }}</dd>
                </div>

                <!-- <div class="flex flex-col">
                    <dt class="text-sm text-gray-500">{{ trans("Added date") }}</dt>
                    <dd class="text-sm font-medium">{{ useFormatTime(data?.product?.data?.created_at) }}</dd>
                </div> -->

                <div class="flex flex-col">
                    <dt class="text-sm text-gray-500">{{ trans("Price") }}</dt>
                    <dd class="text-sm font-medium">{{ locale.currencyFormat(data?.product?.data?.currency_code,
                        data?.product?.data?.price) }}</dd>
                </div>

                <div class="flex flex-col">
                    <dt class="text-sm text-gray-500">{{ trans("Description") }}</dt>
                    <dd v-if="data?.product?.data?.description_title"
                        class="text-sm font-medium bg-gray-100 px-3 py-2 rounded shadow"
                        v-html="data?.product?.data?.description_title"></dd>
                    <dd class="text-sm font-medium bg-gray-100 px-3 py-2 rounded shadow"
                        v-html="data?.product?.data?.description ?? '-'"></dd>
                    <dd v-if="data?.product?.data?.description_extra"
                        class="text-sm font-medium bg-gray-100 px-3 py-2 rounded shadow"
                        v-html="data?.product?.data?.description_extra ?? '-'"></dd>
                </div>

                <ProductContentsIris :product="props.data.product.data" :setting="{
                    product_specs: true, faqs: false
                }" :styleData="{}" :full-width="true" />
            </dl>
        </section>
    </div>
</template>
