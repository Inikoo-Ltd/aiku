<script setup lang="ts">

import { faChevronCircleLeft, faChevronCircleRight, faImage } from '@far'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import DepartementRender from './DepartementRender.vue';

const props = defineProps<{
  fieldValue: {
    departments: {
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
    <div v-for="(item, index) in props.fieldValue.departments" :key="index">
      <DepartementRender :data="item" />
    </div>
  </div>
</template>
