<script setup lang="ts">
import { ref } from "vue"
import draggable from "vuedraggable"
import Image from "@/Components/Image.vue"

const props = defineProps<{
  selectedWeblock: String
  webBlockTypes: {
    data: Array<any>
  }
}>()

const emits = defineEmits<{
  (e: 'pick-block', value: object): void
}>()

// Fungsi move yang mengembalikan false berarti drag tidak diizinkan
const allowMove = () => false
</script>

<template>
  <draggable :list="webBlockTypes.data" ghost-class="ghost" group="column" itemKey="id" class="mt-4 space-y-4"
    :move="allowMove" :disabled="true">
    <template #item="{ element }">
      <div @click="() => emits('pick-block', element)" :class="[
        'bg-white border rounded shadow-sm overflow-hidden cursor-pointer',
        selectedWeblock === element.code
          ? 'border-indigo-500 ring-2 ring-indigo-300'
          : 'border-gray-200'
      ]">
        <!-- Gambar -->
        <div class="w-full bg-gray-100 flex items-center justify-center">
          <Image :src="element.screenshot" class="w-full h-auto object-contain"
            :alt="`Screenshot of ${element.name}`" />
        </div>

        <!-- Nama -->
        <div class="text-xs text-center py-2 font-bold text-gray-700">
          {{ element.name }}
        </div>
      </div>

    </template>
  </draggable>
</template>

<style scoped lang="scss">
.ghost {
  opacity: 0.5;
  background-color: #e2e8f0;
  border: 2px dashed #4F46E5;
}
</style>
