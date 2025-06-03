<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fortawesome/free-solid-svg-icons'
import { notify } from '@kyvg/vue3-notification'
import { useForm } from '@inertiajs/vue3'
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

const form = useForm({
  name: props.data.name || '',
  description: props.data.description || '',
  image: props.data.image || null,
})

const previewUrl = ref<string | null>(null)

// Update preview URL whenever the image changes
watch(
  () => form.image,
  (newImage, oldImage) => {
    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value) // clean old URL
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
    form.image = target.files[0]
  }
}

const submitForm = () => {
    console.log('Submitting form data:', form.data())
  form.submit('patch', route(props.saveRoute.name, { productCategory: props.data.id }), {
    onSuccess: (data) => {
        console.log(data)
      notify({ title: 'Success', text: 'Department saved.', type: 'success' })
      emit('saved', { ...props.data, ...form.data() })
    },
    onError: () => {
      notify({ title: 'Failed', text: 'Please check the form again.', type: 'error' })
    },
  })
}
</script>

<template>
  <div>
    <!-- Name Field -->
    <div class="mb-6">
      <label for="name" class="block mb-1 text-sm font-semibold text-gray-700">Department Name</label>
      <InputText id="name" v-model="form.name" class="w-full" placeholder="Enter department name" />
    </div>

    <!-- Description Field -->
    <div class="mb-6">
      <label for="description" class="block mb-1 text-sm font-semibold text-gray-700">Description</label>
      <Textarea id="description" v-model="form.description" rows="4" class="w-full" placeholder="Enter description" />
    </div>

    <!-- Image Upload and Preview -->
    <div class="mb-6">
      <label class="block mb-2 text-sm font-semibold text-gray-700">Image</label>
      <div
        class="w-full h-40 border rounded-lg shadow flex items-center justify-center bg-gray-50 overflow-hidden cursor-pointer relative"
      >
        <template v-if="previewUrl">
          <img :src="previewUrl" alt="Preview" class="w-full h-full object-cover" />
        </template>
        <template v-else>
          <FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
        </template>

        <!-- File input is invisible but covers entire preview box -->
        <input
          type="file"
          accept="image/*"
          class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
          @change="onFileChange"
        />
      </div>
    </div>

    <!-- Submit Button -->
    <Button label="Submit" icon="pi pi-check" full @click="submitForm" />
  </div>
</template>
