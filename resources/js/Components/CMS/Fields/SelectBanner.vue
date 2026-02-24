<script setup lang="ts">
import { computed } from 'vue'
import { faCopy } from '@fal'
import { faEye, faEyeSlash } from '@far'
import { faTimesCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { routeType } from '@/types/route'
import Image from '@/Components/Image.vue'

library.add(faCopy, faEye, faEyeSlash, faTimesCircle, faSpinnerThird)

const props = defineProps<{
  modelValue: string | number | null | undefined
  fetchRoute: routeType
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: number | string | null): void
}>()

const value = computed({
  get: () => props.modelValue,
  set: v => emits('update:modelValue', { id: v.id, name: v.name, slug: v.slug })
})
</script>

<template>
  <PureMultiselectInfiniteScroll v-model="value" :fetch-route="fetchRoute" :object="true" labelProp="name"
    value-prop="id">

    <!-- selected label -->
    <template #singlelabel="{ value }">
      <div class="w-full flex items-center pl-3 pr-2 truncate text-sm">
        <span class="truncate font-medium text-gray-800">
          {{ value?.name ?? value?.slug }}
        </span>
      </div>
    </template>

    <!-- option -->
    <template #option="{ option, isSelected }">
      <div :key="option.slug"
        class="group w-full bg-white rounded-xl border border-gray-200 overflow-hidden cursor-pointer transition-all duration-200 hover:shadow-lg hover:-translate-y-[2px]"
        :class="isSelected ? 'ring-2 ring-primary/40 border-primary/40' : ''">
        <!-- image -->
        <div class="h-36 bg-gray-50 flex items-center justify-center overflow-hidden">
          <Image v-if="option.image_thumbnail" :src="option.image_thumbnail"
            class="object-contain w-full h-full transition-transform duration-300 group-hover:scale-105" />
          <div v-else class="text-xs text-gray-400">
            No image
          </div>
        </div>

        <!-- content -->
        <div class="px-3 py-2">
          <h3 class="text-sm font-semibold text-gray-900 truncate">
            {{ option.name }}
          </h3>

          <p v-if="option.slug" class="text-xs text-gray-400 truncate mt-0.5">
            {{ option.slug }}
          </p>
        </div>
      </div>
    </template>

  </PureMultiselectInfiniteScroll>
</template>