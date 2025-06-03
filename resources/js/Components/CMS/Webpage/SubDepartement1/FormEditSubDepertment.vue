<script setup lang="ts">
import { ref, computed } from 'vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from '@/Components/Elements/Buttons/Button.vue'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import Dialog from 'primevue/dialog'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fortawesome/free-solid-svg-icons'
import Image from '@/Components/Image.vue'
import { notify } from '@kyvg/vue3-notification'
import { router as Inertia, router } from '@inertiajs/vue3'
import axios from 'axios'
import { data } from 'autoprefixer'

const props = defineProps<{
  data: {
    department_name: string
    description: string
    image?: string
  }
  saveRoute: routeType
}>()

const form = ref({
  name: props.data.department_name || '',
  description: props.data.description || '',
  image: props.data.image || null,
})

const isGalleryModalOpen = ref(false)

const imagePreview = computed(() =>
  form.value.image instanceof File
    ? URL.createObjectURL(form.value.image)
    : form.value.image
)

const onChangeImage = (image) => {
  form.value.image = image[0]?.source?.original_url || null
  isGalleryModalOpen.value = false
}

const onUpload = async (files, clear) => {
  const formData = new FormData()
  files.forEach((file, index) => {
    formData.append(`images[${index}]`, file)
  })

  try {
    const response = await axios.patch(
      route(props.saveRoute.name, {productCategory : props.data.id}),
      {image : form.value.image},
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )
    console.log(response)
    form.image = response.data
    clear()
  } catch (error) {
    console.log(error)
    notify({ title: 'Upload Failed', text: 'Something went wrong.', type: 'error' })
  }
}

const submitForm = () => {
  router.patch(route(props.saveRoute.name, route(props.saveRoute.name, {productCategory : props.data.id}),), form.value, {
    onSuccess: () => {
      notify({ title: 'Success', text: 'Department saved.', type: 'success' })
    },
    onError: (errors) => {
      notify({ title: 'Failed', text: 'Please check the form again.', type: 'error' })
    },
  })
}
console.log('sdfsdf',props)
</script>


<template>
  
    <div >
        <!-- Name Field -->
        <div  class="mb-6"> 
            <label for="name" class="block mb-1 text-sm font-semibold text-gray-700">Department Name</label>
            <InputText id="name" v-model="form.name" class="w-full" placeholder="Enter department name" />
        </div>

        <!-- Description Field -->
        <div  class="mb-6">
            <label for="description" class="block mb-1 text-sm font-semibold text-gray-700">Description</label>
            <Textarea id="description" v-model="form.description" rows="4" class="w-full"
                placeholder="Enter description" />
        </div>

        <!-- Image Upload with Preview -->
        <div class="mb-6">
            <label class="block mb-2 text-sm font-semibold text-gray-700">Image</label>
            <div class="flex items-center gap-4">
                <div @click="isGalleryModalOpen = true"
                    class="w-full h-40 border rounded-lg shadow flex items-center justify-center bg-gray-50 overflow-hidden">
                    <template v-if="form.image">
                        <Image :src="form.image" alt="Preview" class="w-full h-full object-cover" />
                    </template>
                    <template v-else>
                        <FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400"></FontAwesomeIcon>
                    </template>
                </div>
            </div>
        </div>


        <!-- Submit Button -->
        <Button label="Submit" icon="pi pi-check" full @click="submitForm" />

        <!-- Gallery Modal using PrimeVue Dialog -->
        <Dialog v-model:visible="isGalleryModalOpen" modal header="Select Image" :style="{ width: '80vw' }">
            <GalleryManagement :maxSelected="1" :uploadRoute="props.saveRoute"
                :closePopup="() => (isGalleryModalOpen = false)" @submitSelectedImages="onChangeImage"
                :submitUpload="onUpload" />
        </Dialog>
    </div>
</template>
