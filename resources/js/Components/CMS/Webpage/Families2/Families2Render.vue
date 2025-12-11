<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  data: {
    name: string
    description: string
    images: { source: string }[]
  }
  screenType: 'mobile' | 'tablet' | 'desktop'
  style: any
}>()
</script>

<template>
  <div
    class="inline-flex items-center bg-black rounded-3xl border border-white/10
           px-4 py-2 sm:px-5 sm:py-3 justify-center h-auto 
           text-white text-base font-semibold leading-tight text-center
           overflow-hidden sm:overflow-visible"
    :class="{
      '!w-[230px]': screenType === 'mobile'
    }"
    :style="style"
  >
    <span
      class="whitespace-normal break-words w-full text-center
             sm:whitespace-normal sm:max-w-full"
             :class="{
      '!w-[230px]': screenType === 'mobile'
    }"
    >
      {{ data?.name }}
    </span>
  </div>
</template>
