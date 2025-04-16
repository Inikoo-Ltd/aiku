<script setup lang="ts">

import { faChevronCircleLeft, faChevronCircleRight, faImage } from '@far'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps<{
  fieldValue: {
    family: {
      name: string
      description: string
      images: { source: string }[] 
    }[] 
  }
  webpageData?: any
  blockData?: Object
}>()

console.log(props)



</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="(item, index) in props.fieldValue.departments"
        :key="index"
        class="bg-white rounded-lg shadow hover:shadow-md transition duration-300"
      >
        <img
          v-if="item.images"
          :src="item.images[0]?.source"
          alt="Family Image"
          class="w-full h-40 object-cover rounded-t-lg"
        />
        <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
          <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
        </div>
        <div class="p-2">
          <h3 class="text-center font-medium text-sm truncate">
            {{ item.name }}
          </h3>
        </div>
      </div>
    </div>
  </template>
