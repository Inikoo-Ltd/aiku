<script setup lang="ts">
import { onMounted, ref } from "vue"
import Dialog from "./Dialog.vue"
import axios from "axios"
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


const imageListRef = ref<ImageData[]>([])

function closeDialog() {
  emit("close")
}

function onDropImage(acceptedFiles: any[]) {
  if (acceptedFiles.length === 0) {
    return
  }

  const formData = new FormData()
  formData.append("file", acceptedFiles[0])

  axios
    .post("http://localhost:8080/files", formData, {
      headers: {
        "Content-type": "multipart/form-data",
      },
    })
    .then(() => {
      loadData()
    })
}

function loadData() {
  axios.get("http://localhost:8080/files").then((result) => {
    imageListRef.value = result.data
  })
}


const onPick = (e) =>{
  insertImage(e[0].source.original)
}

function insertImage(url: string) {
  emit("insert", url)
  closeDialog()
}

onMounted(() => {
  loadData()
})
</script>


<template>
    <Dialog :title="trans('Add image')" :show="show" @close="closeDialog" class="w-[700px]">
      <GalleryManagement 
				:maxSelected="1" 
				:closePopup="closeDialog" 
        @selectImage="(e)=>{console.log(e)}"
        @submitSelectedImages="onPick"
				:submitUpload="()=>console.log('sdsd')"
				:uploadRoute="uploadImageRoute"
			/>
    </Dialog>
  </template>
  
