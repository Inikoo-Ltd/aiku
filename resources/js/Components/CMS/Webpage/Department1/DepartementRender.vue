<script setup lang="ts">
import { faImage } from '@far'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed } from 'vue';
import Image from '@/Components/Image.vue';

const props = defineProps<{
  data: {
    name?: string
    image?: Array<{ source: string }>
  }
}>()

/* const imageSource = computed(() => {
  const src = props.data?.images?.[0]?.source || ''
  const isBase64 = src.startsWith('data:image/')
  const isUrl = src.startsWith('http') || src.startsWith('/')
  return isBase64 || isUrl ? src : null
}) */

console.log(props.data)

</script>

<template>
  <div class="bg-white rounded-lg shadow hover:shadow-md transition duration-300">
    <Image
      v-if="data.image"
      :src="data.image"
      :imageCover="true"
      class="w-full h-40 object-cover rounded-t-lg"
    />
    <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
      <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
    </div>
    <div class="p-2">
      <h3 class="text-center font-medium text-sm truncate">
        {{ data.name || 'No Name' }}
      </h3>
    </div>
  </div>
</template>
