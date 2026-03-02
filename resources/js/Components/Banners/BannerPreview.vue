<script setup lang="ts">
import { computed } from 'vue'
import { useRangeFromNow } from '@/Composables/useFormatTime'
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import Image from '@/Components/Image.vue'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
  data: {
    type: string
    compiled_layout: any
    published_snapshot?: {
      publisher?: string
      publisher_avatar?: string
      comment?: string
      published_at?: string
    }
  }
  ratio?: string
}>()

/*
  Ratio logic:
  - "4/1"
  - "1/1"
  - "1.77"
  - undefined → fallback based on type
*/
const ratioStyle = computed(() => {
  if (!props.ratio) {
    return props.data.type === 'landscape'
      ? { paddingTop: '25%' }     // 4/1
      : { paddingTop: '100%' }    // 1/1
  }

  if (props.ratio.includes('/')) {
    const [w, h] = props.ratio.split('/').map(Number)
    if (w > 0 && h > 0) {
      return { paddingTop: `${(h / w) * 100}%` }
    }
  }

  const numeric = Number(props.ratio)
  if (!isNaN(numeric) && numeric > 0) {
    return { paddingTop: `${(1 / numeric) * 100}%` }
  }

  return props.data.type === 'landscape'
    ? { paddingTop: '25%' }
    : { paddingTop: '100%' }
})

const publishedAgo = computed(() => {
  const publishedAt = props.data?.published_snapshot?.published_at
  if (!publishedAt) return null
  return `${useRangeFromNow(publishedAt)} ago`
})
</script>

<template>
  <div class="w-full bg-white border border-gray-300 rounded-md overflow-hidden">

    <!-- Header -->
    <div
      v-if="data.published_snapshot"
      class="w-full flex items-center justify-between py-3 px-4 border-b"
    >
      <div class="flex gap-2 items-center min-w-0">
        <div
          v-if="data?.published_snapshot?.publisher_avatar"
          class="h-6 w-6 rounded-full overflow-hidden ring-1 ring-gray-300 shrink-0"
        >
          <Image :src="data.published_snapshot.publisher_avatar" />
        </div>

        <div
          v-if="data.published_snapshot?.publisher"
          class="font-semibold text-sm truncate"
        >
          {{ data.published_snapshot.publisher }}
        </div>

        <div v-else class="text-gray-400 italic text-sm">
          {{ trans("Not published yet") }}
        </div>

        <div
          v-if="data.published_snapshot?.comment"
          class="text-xs text-gray-500 italic truncate"
        >
          ({{ data.published_snapshot.comment }})
        </div>
      </div>

      <div
        v-if="data.published_snapshot?.published_at"
        class="text-xs text-gray-600 shrink-0"
      >
        {{ publishedAgo }}
      </div>
    </div>

    <!-- Ratio Container -->
    <div class="relative w-full overflow-hidden">

      <!-- Creates height based on width -->
      <div :style="ratioStyle" />

      <!-- Absolute content -->
      <div class="absolute inset-0">
        <component
          :is="data.type === 'landscape' ? SliderLandscape : SliderSquare"
          :data="data.compiled_layout"
          :production="true"
          :ratio="ratio"
          class="w-full h-full"
          view="desktop"
        />
      </div>

    </div>

  </div>
</template>