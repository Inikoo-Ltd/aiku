<script setup lang="ts">

import EmptyState from '@/Components/Utils/EmptyState.vue';
import DepartmentRender from './DepartmentRender.vue';

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

</script>

<template>
  <div v-if="props.fieldValue?.departments"  class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4">
    <div v-for="(item, index) in props.fieldValue?.departments" :key="index">
      <DepartmentRender :data="item" />
    </div>
  </div>
   <EmptyState :data="{title:'Empty Departments'}" />
</template>
