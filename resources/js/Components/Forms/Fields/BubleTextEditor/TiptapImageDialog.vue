<script setup lang="ts">
import Dialog from 'primevue/dialog'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import { ref, watch } from 'vue'

const props = defineProps<{
  show: boolean
  uploadImageRoute?: routeType
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'insert', url: string): void
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

function insertImage(url: string) {
  emit('insert', url)
  closeDialog()
}

function onPick(e: any) {
  insertImage(e[0].source.original)
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="trans('Select Image')"
    class="w-full max-w-5xl"
    dismissableMask
    @hide="closeDialog"
  >
    <GalleryManagement
      :maxSelected="1"
      :closePopup="closeDialog"
      @submitSelectedImages="onPick"
      @onSuccessUpload="(value) => insertImage(value.data[0].source.original)"
      :uploadRoute="uploadImageRoute"
    />
  </Dialog>
</template>
