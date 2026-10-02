<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 12 May 2026 09:30:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from 'vue'
import type { Image as ImageProxy } from '@/types/Image'
import { expandGallery } from '@/Common/Composables/useCompactImage'

const fallbackPath = '/fallback/fallback.svg'

const props = withDefaults(defineProps<{
  src?: ImageProxy | null
  imageCover?: boolean
  alt?: string
  class?: string
  style?: Record<string, any>

  width?: string
  height?: string

  sizes?: string
  srcset?: { original?: string; avif?: string; webp?: string }

  preload?: boolean

  imgAttributes?: {
    fetchpriority?: 'high' | 'low'
    loading?: 'lazy' | 'eager'
    decoding?: 'async' | 'sync' | 'auto'
  }
}>(), {
  src: () => ({ original: fallbackPath }),
  preload: false,
  imgAttributes: () => ({
    loading: 'lazy',
    decoding: 'async',
  }),
  alt: 'image',
})

const emits = defineEmits<{
  (e: 'onLoadImage'): void
}>()

const imageSrc = computed(() => expandGallery(props.src) as ImageProxy | null)


const parsePx = (value?: string): number | undefined => {
  if (!value) return undefined
  if (value.endsWith('px')) return Number(value.replace('px', ''))
  if (!isNaN(Number(value))) return Number(value)
  return undefined
}

const baseWidth = computed(() => parsePx(props.width))
const baseHeight = computed(() => parsePx(props.height))


const buildDensitySrcSet = (url?: string, url2x?: string) => {
  if (!url) return undefined

  return url2x ? `${url} 1x, ${url2x} 2x` : url
}

const avif = computed(() => props.srcset?.avif ?? buildDensitySrcSet(imageSrc.value?.avif, imageSrc.value?.avif_2x))
const webp = computed(() => props.srcset?.webp ?? buildDensitySrcSet(imageSrc.value?.webp, imageSrc.value?.webp_2x))
const original = computed(() => props.srcset?.original ?? buildDensitySrcSet(imageSrc.value?.original, imageSrc.value?.original_2x))

const defaultSrc = computed(() => imageSrc.value?.original || fallbackPath)
</script>

<template>
  <picture :class="[props.class ?? 'w-full h-full flex justify-center items-center']">
    <source v-if="avif" type="image/avif" :srcset="avif" :sizes="sizes" />
    <source v-if="webp" type="image/webp" :srcset="webp" :sizes="sizes" />

    <img
      :src="defaultSrc"
      :srcset="original"
      :sizes="sizes"
      :alt="alt"
      :width="baseWidth"
      :height="baseHeight"
      :style="{ height: 'inherit', ...style }"
      :class="imageCover ? 'w-full h-full object-cover' : undefined"
      v-bind="preload ? { ...imgAttributes, loading: 'eager', fetchpriority: 'high' } : imgAttributes"
      @load="emits('onLoadImage')"
    />
  </picture>
</template>
