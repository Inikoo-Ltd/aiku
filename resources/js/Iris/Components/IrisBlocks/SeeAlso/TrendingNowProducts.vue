<script setup lang="ts">
import { computed } from "vue"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = withDefaults(defineProps<{
  products: any[]
  perRow?: {
    desktop?: number
    tablet?: number
    mobile?: number
  }
  screenType?: "mobile" | "tablet" | "desktop"
}>(), {
  screenType: "desktop",
})

const columns = computed(() => {
  return {
    desktop: props.perRow?.desktop ?? 5,
    tablet: props.perRow?.tablet ?? 4,
    mobile: props.perRow?.mobile ?? 2,
  }[props.screenType] ?? 5
})

const visibleProducts = computed(() => (props.products ?? []).slice(0, columns.value))

const getImage = (product: any) => {
  return product?.web_images?.main?.gallery ?? product?.web_images?.main?.original ?? null
}
</script>

<template>
  <div class="mx-auto w-full max-w-[1400px] px-4 py-6 2xl:max-w-[1700px] 2xl:px-10 2xl:py-8">
    <div class="grid gap-x-6 gap-y-8 2xl:gap-x-10 2xl:gap-y-10" :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }">
      <component
        :is="product.url ? LinkIris : 'div'"
        v-for="product in visibleProducts"
        :key="product.id ?? product.slug"
        :href="product.url"
        type="internal"
        class="group flex flex-col items-center text-center"
      >
        <div class="relative w-full max-w-[180px] aspect-square overflow-hidden bg-white 2xl:max-w-[240px]">
          <Image
            v-if="getImage(product)"
            :src="getImage(product)"
            :alt="product.name"
            class="absolute inset-0 w-full h-full"
            :style="{ objectFit: 'contain', objectPosition: 'center' }"
          />
          <FontAwesomeIcon
            v-else
            icon="fal fa-image"
            class="opacity-20 text-3xl absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2 2xl:text-5xl"
          />
        </div>

        <div class="mt-3 max-w-[180px] text-sm leading-tight text-[#1d2d44] group-hover:text-gray-500 2xl:mt-4 2xl:max-w-[240px] 2xl:text-base">
          {{ product.name }}
        </div>
      </component>
    </div>
  </div>
</template>
