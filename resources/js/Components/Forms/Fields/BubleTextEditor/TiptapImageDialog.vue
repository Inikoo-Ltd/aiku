<script setup lang="ts">
import GalleryDialog from '@/Components/Utils/GalleryManagement/GalleryDialog.vue'
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'
import { GalleryCategory } from '@/Composables/useShopGalleryCategories'
import { ref, watch } from 'vue'

const props = defineProps<{
  show: boolean
  uploadImageRoute?: routeType
  imagesUploadedRoute?: routeType
  imageCategories?: GalleryCategory[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'insert', url: string, alt?: string): void
}>()

const visible = ref(props.show)

watch(
  () => props.show,
  (val) => {
    visible.value = val
  }
)

function closeDialog() {
  visible.value = false
  emit('close')
}

function insertImage(url: string, alt?: string) {  
  emit('insert', url, alt)
  closeDialog()
}

function onPick(e: any) {
  insertImage(e[0].source.original, e[0].alt || e[0].name)
}

function onSuccessUpload(value: any) {
  insertImage(value.data[0].source.original, value.data[0].alt || value.data[0].name)
}
</script>

<template>
  <GalleryDialog
    v-model:visible="visible"
    :header="ctrans('Select Image')"
    :imageCategories="imageCategories"
    :maxSelected="1"
    :closePopup="closeDialog"
    :uploadRoute="uploadImageRoute"
    :imagesUploadedRoutes="imagesUploadedRoute"
    :tabs="uploadImageRoute ? undefined : ['images_uploaded', 'stock_images']"
    @update:visible="(isVisible) => { if (!isVisible) closeDialog() }"
    @submitSelectedImages="onPick"
    @onSuccessUpload="onSuccessUpload"
  />
</template>
