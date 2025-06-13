<script setup lang="ts">
import { reactive } from 'vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ImageCropper from '@/Components/Forms/Fields/ImageCropSquare.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'

const props = defineProps<{
  data: {
    id: number
    name: string
    description: string
    image?: { original: string }
  }
  saveRoute: routeType
}>()

const emit = defineEmits(['saved'])

const form = reactive({
  name: props.data.name,
  description: props.data.description,
  image: props.data.image,
  errors: {} as Record<string, string>,
  recentlySuccessful: false
})

const submitForm = async () => {
  form.errors = {}
  const formData = new FormData()
  formData.append('name', form.name)
  formData.append('description', form.description)
  formData.append('_method', 'PATCH')

  if (form.image instanceof File) {
    formData.append('image', form.image)
  }

  try {
    const url = route(props.saveRoute.name, { productCategory: props.data.id })
    const response = await axios.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    notify({ title: 'Success', text: 'The Product Category has been successfully updated.', type: 'success' })
    form.recentlySuccessful = true

    emit('saved', {
      id: props.data.id,
      name: form.name,
      description: form.description,
      image: response.data.data.image || form.image
    })
  } catch (error) {
    form.errors = error?.response?.data?.errors || {}
    notify({ title: 'Update Failed', text: 'Please fix the errors and try again.', type: 'error' })
  }
}
</script>

<template>
  <div>
    <div class="mb-6">
      <label for="name" class="block mb-1 text-sm font-semibold text-gray-700">Department Name</label>
      <InputText id="name" v-model="form.name" class="w-full" placeholder="Enter department name"
        @update:model-value="form.errors.name = undefined" />
      <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
    </div>

    <div class="mb-6">
      <label for="description" class="block mb-1 text-sm font-semibold text-gray-700">Description</label>
      <Textarea id="description" v-model="form.description" rows="4" class="w-full" placeholder="Enter description"
        @update:model-value="form.errors.description = undefined" />
      <p v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</p>
    </div>

    <div class="mb-6">
      <label class="block mb-2 text-sm font-semibold text-gray-700">Image</label>
      <ImageCropper
        :form="form"
        fieldName="image"
        :fieldData="{
          options: {
            aspectRatio: { width: 1, height: 1 }
          }
        }"
      />
    </div>

    <Button label="Submit" icon="pi pi-check" full @click="submitForm" />
  </div>
</template>
