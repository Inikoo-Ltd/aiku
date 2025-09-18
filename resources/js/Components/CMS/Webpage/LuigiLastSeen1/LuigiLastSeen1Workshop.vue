<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"

// import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'


import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
// import axios from "axios"
import { trans } from "laravel-vue-i18n"
library.add(faChevronLeft, faChevronRight)

// interface ProductHits {
//     attributes: {
//         image_link: string
//         price: string
//         formatted_price: string
//         department: string[]
//         category: string[]
//         product_code: string[]
//         stock_qty: string[]
//         title: string
//         web_url: string[]
//     }
// }
const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: {}
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()


const layout = inject('layout', retinaLayoutStructure)

onMounted(()=> {
})
</script>

<template>
    <div id="luigi-last-seen-1-workshop" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div class="px-4 py-6 pb-2 text-3xl font-semibold">
            <EditorV2
                v-model="modelValue.title"
                @focus="() => {
                    sendMessageToParent('activeBlock', indexBlock)
                }"
                @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                    name: webpageData.images_upload_route.name,
                    parameters: { modelHasWebBlocks: blockData?.id }
                }"
            />
        </div>

        <div class="py-4">
            <div class="h-48 flex text-lg font-semibold flex-col items-center justify-center  w-full bg-gray-200 border border-gray-300">
                <div>{{ trans("No recommendations to preview") }}</div>
                <div class="text-sm italic text-gray-500 font-normal">{{ trans("Last Seen is very specific to user behavior, will show real recommendations on live website.") }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.swiper-nav-button {
    @apply absolute top-1/2 transform -translate-y-1/2 z-10 bg-white border border-gray-300 rounded-full shadow-md p-2 hover:bg-gray-100 transition-all duration-300;
}

.swiper-nav-button svg {
    @apply text-gray-700 w-4 h-4;
}
</style>
