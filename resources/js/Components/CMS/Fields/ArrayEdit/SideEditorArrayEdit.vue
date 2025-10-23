<script setup lang="ts">
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { routeType } from '@/types/route'
import { inject } from 'vue';

const modelValue = defineModel<any>() // enables v-model
const props = defineProps<{
  blueprint: any
  uploadRoutes: routeType
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: PaddingMarginModel): void
}>()

const activeChildBlockArrayBlock: any = inject("activeChildBlockArrayBlock", null);


</script>

<template>
  <SideEditor :blueprint="blueprint" v-model="modelValue" :uploadImageRoute="uploadRoutes"
    :panelOpen="activeChildBlockArrayBlock" @update:modelValue="(e) => { emit('update:modelValue', e) }" />
</template>


<style scoped>
</style>