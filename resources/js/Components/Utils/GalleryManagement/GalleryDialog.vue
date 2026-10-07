<script setup lang="ts">
import { computed } from 'vue'
import Dialog from 'primevue/dialog'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import { ctrans } from '@/Composables/useTrans'
import { GalleryCategory, useShopGalleryCategories } from '@/Composables/useShopGalleryCategories'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    header?: string
    imageCategories?: GalleryCategory[]
}>()

const visible = defineModel<boolean>('visible', { default: false })

const shopGalleryCategories = useShopGalleryCategories()
const categories = computed(() => props.imageCategories ?? shopGalleryCategories.value)
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="header ?? ctrans('Select Image')"
        class="w-full max-w-5xl"
        dismissableMask
        :draggable="false"
        :breakpoints="{ '1100px': '95vw' }"
        :pt="{
            root: { class: '!h-[85vh] !max-h-[85vh] flex flex-col' },
            header: { class: '!px-5 !py-3 border-b border-gray-200' },
            content: { class: 'flex min-h-0 flex-1 flex-col !px-5 !pb-4 !pt-2' },
        }"
    >
        <GalleryManagement
            fill
            class="min-h-0 flex-1"
            :imageCategories="categories"
            :imagesUploadedLabel="categories.length ? ctrans('Other images') : undefined"
            :closePopup="() => (visible = false)"
            v-bind="$attrs"
        />
    </Dialog>
</template>
