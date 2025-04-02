<script setup lang="ts">
import Dialog from "./Dialog.vue"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { routeType } from "@/types/route";
import { trans } from "laravel-vue-i18n"

defineProps<{
  show: boolean,
  uploadImageRoute?: routeType
}>()

const emit = defineEmits<{
  (e: "close"): void
  (e: "insert", url: string): void
}>()


function closeDialog() {
  emit("close")
}


const onPick = (e) => {
  insertImage(e[0].source.original)
}

function insertImage(url: string) {
  emit("insert", url)
  closeDialog()
}


</script>


<template>
  <Dialog  :show="show" @close="closeDialog" class="w-full">
    <GalleryManagement 
      :maxSelected="1" 
      :closePopup="closeDialog" 
      @submitSelectedImages="onPick" 
      @onSuccessUpload="(value) => insertImage(value.data[0].source.original)"
      :uploadRoute="uploadImageRoute"
     />
  </Dialog>
</template>
