<script setup lang="ts">
import { computed } from 'vue'
import type { Image as ImageProxy } from '@/types/Image'

const fallbackPath = '/fallback/fallback.svg'
const DEFAULT_ASPECT_RATIO = 16 / 9
const MAX_DPR = 2

type ResponsiveSize = {
  mobile?: number
  tablet?: number
  desktop?: number
}

const props = withDefaults(defineProps<{
  src?: ImageProxy | null
  imageCover?: boolean
  alt?: string
  class?: string
  style?: Record<string, any>

  width?: string
  height?: string

  responsive?: ResponsiveSize
  responsiveEnabled?: boolean

  imgAttributes?: {
    fetchpriority?: 'high' | 'low'
    loading?: 'lazy' | 'eager'
    decoding?: 'async' | 'sync' | 'auto'
  }
}>(), {
  src: () => ({ original: fallbackPath }),
  responsiveEnabled: true,
  imgAttributes: () => ({
    loading: 'lazy',
    decoding: 'async',
  }),
})

const emits = defineEmits<{
  (e: 'onLoadImage'): void
}>()

/* ---------------- DPR ---------------- */

const dpr =
  typeof window !== 'undefined'
    ? Math.min(window.devicePixelRatio || 1, MAX_DPR)
    : 1

/* ---------------- Utils ---------------- */

const parsePx = (value?: string): number | undefined => {
  if (!value) return undefined
  if (value.endsWith('px')) return Number(value.replace('px', ''))
  if (!isNaN(Number(value))) return Number(value)
  return undefined
}

const baseWidth = computed(() => parsePx(props.width))
const baseHeight = computed(() => parsePx(props.height))

/* ---------------- Responsive widths ---------------- */

const responsiveWidths = computed<ResponsiveSize>(() => {
  if (props.responsive) return props.responsive

  if (baseWidth.value) {
    return {
      mobile: Math.min(baseWidth.value, 360),
      tablet: baseWidth.value,
      desktop: Math.round(baseWidth.value * dpr),
    }
  }

  if (baseHeight.value) {
    const w = Math.round(baseHeight.value * DEFAULT_ASPECT_RATIO)
    return {
      mobile: Math.min(w, 360),
      tablet: w,
      desktop: Math.round(w * dpr),
    }
  }

  return {
    mobile: 360,
    tablet: 768,
    desktop: Math.round(1280 * dpr),
  }
})

/* ---------------- Responsive heights ---------------- */

const responsiveHeights = computed(() => {
  const ratio =
    baseWidth.value && baseHeight.value
      ? baseWidth.value / baseHeight.value
      : DEFAULT_ASPECT_RATIO

  return Object.fromEntries(
    Object.entries(responsiveWidths.value).map(([k, w]) => [
      k,
      Math.round(w! / ratio),
    ])
  )
})

/* ---------------- Cloudflare URL ---------------- */

const buildCFUrl = (url: string, width?: number, height?: number) => {
  const params = new URLSearchParams()

  if (width) params.set('width', String(width))
  if (height) params.set('height', String(height))

  params.set('fit', props.imageCover ? 'cover' : 'contain')
  params.set('format', 'auto')

  return `${url}?${params.toString()}`
}

/* ---------------- SrcSet ---------------- */

const buildSrcSet = (url?: string) => {
  if (!props.responsiveEnabled || !url) return undefined

  return Object.keys(responsiveWidths.value)
    .map(key => {
      const w = responsiveWidths.value[key as keyof ResponsiveSize]
      const h = responsiveHeights.value[key]
      return `${buildCFUrl(url, w, h)} ${w}w`
    })
    .join(', ')
}

const avif = computed(() => buildSrcSet(props.src?.avif))
const webp = computed(() => buildSrcSet(props.src?.webp))
const original = computed(() => buildSrcSet(props.src?.original))

/* ---------------- Default src ---------------- */

const defaultSrc = computed(() => {
  const w = responsiveWidths.value.desktop
  const h = responsiveHeights.value.desktop

  return buildCFUrl(
    props.src?.original || fallbackPath,
    props.responsiveEnabled ? w : undefined,
    props.responsiveEnabled ? h : undefined
  )
})

/* ---------------- Sizes ---------------- */

const sizes = computed(() => {
  if (!props.responsiveEnabled) return undefined

  return `
    (max-width: 640px) 100vw,
    (max-width: 1024px) 80vw,
    ${responsiveWidths.value.tablet}px
  `.trim()
})
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
      :width="width"
      :height="height"
      :style="{ height: 'inherit', ...style }"
      :class="imageCover ? 'w-full h-full object-cover' : undefined"
      v-bind="imgAttributes"
      @load="emits('onLoadImage')"
    />
  </picture>
</template>
