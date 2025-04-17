<script setup lang="ts">

import { faChevronCircleLeft, faChevronCircleRight, faImage } from '@far'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import DepartementRender from './DepartementRender.vue';

const props = defineProps<{
  data : {}
}>()

console.log(props)



</script>

<template>
      <div
        class="bg-white rounded-lg shadow hover:shadow-md transition duration-300"
      >
        <img
          v-if="data?.images"
          :src="data.images[0]?.source"
          alt="Family Image"
          class="w-full h-40 object-cover rounded-t-lg"
        />
        <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
          <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
        </div>
        <div class="p-2">
          <h3 class="text-center font-medium text-sm truncate">
            {{ data.name }}
          </h3>
        </div>
      </div>
  </template>
