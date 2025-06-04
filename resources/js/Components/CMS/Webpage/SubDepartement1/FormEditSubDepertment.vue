<script setup lang="ts">
import { ref, watch } from 'vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fortawesome/free-solid-svg-icons'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { routeType } from '@/types/route'

const props = defineProps<{
  data: {
    id: number
    name: string
    description: string
    image?: string
  }
  saveRoute: routeType
}>()

const emit = defineEmits(['saved'])

const name = ref(props.data.name)
const description = ref(props.data.description)
const image = ref<string | File | null>(props?.data?.image?.original || null)

const previewUrl = ref<string | null>(null)

// Update preview saat image berubah
watch(
  () => image.value,
  (newImage, oldImage) => {
    if (previewUrl.value && oldImage instanceof File) {
      URL.revokeObjectURL(previewUrl.value)
      previewUrl.value = null
    }
    if (newImage instanceof File) {
      previewUrl.value = URL.createObjectURL(newImage)
    } else if (typeof newImage === 'string') {
      previewUrl.value = newImage
    }
  },
  { immediate: true }
)

const onFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    image.value = target.files[0]
  }
}

const submitForm = async () => {
  const formData = new FormData()

  formData.append('name', name.value)
  formData.append('description', description.value)
  formData.append('_method', 'PATCH')

  // Jika image adalah File (baru upload), masukkan ke FormData
  if (image.value instanceof File) {
    formData.append('image', image.value, image.value.name)
  }
  
  try {
    const url = route(props.saveRoute.name, { productCategory: props.data.id })

    const response = await axios.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    notify({ title: 'Success', text: 'Data saved successfully.', type: 'success' })

    emit('saved', {
      id: props.data.id,
      name: name.value,
      description: description.value,
      image: response.data.data.image || image.value, // ambil dari response jika ada
    })
  } catch (error) {
    console.error('Save failed:', error)
    notify({ title: 'Failed', text: 'Failed to save data.', type: 'error' })
  }
}
</script>

<template>
  <div>
    <div class="mb-6">
      <label for="name" class="block mb-1 text-sm font-semibold text-gray-700">Department Name</label>
      <InputText id="name" v-model="name" class="w-full" placeholder="Enter department name" />
    </div>

    <div class="mb-6">
      <label for="description" class="block mb-1 text-sm font-semibold text-gray-700">Description</label>
      <Textarea id="description" v-model="description" rows="4" class="w-full" placeholder="Enter description" />
    </div>

    <div class="mb-6">
      <label class="block mb-2 text-sm font-semibold text-gray-700">Image (auto preview)</label>
      <div
        class="w-full h-40 border rounded-lg shadow flex items-center justify-center bg-gray-50 overflow-hidden cursor-pointer relative"
      >
        <template v-if="previewUrl">
          <img :src="previewUrl" alt="Preview" class="w-full h-full object-cover" />
        </template>
        <template v-else>
          <FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
        </template>
        <input
          type="file"
          accept="image/*"
          class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
          @change="onFileChange"
        />
      </div>
    </div>

    <Button label="Submit" icon="pi pi-check" full @click="submitForm" />
  </div>
</template>
