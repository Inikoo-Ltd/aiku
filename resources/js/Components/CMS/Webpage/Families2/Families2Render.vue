<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

defineProps<{
  data: {
    name: string
    description: string
    images: { source: string }[]
  }
  style : any
}>()


</script>

<template>
  <div
    class="w-full bg-black rounded-3xl py-3 px-5 border border-white/10 shadow-[0_0_12px_rgba(0,0,0,0.4)]"
    :style="style"
  >
    <div
      class="mt-1 px-2 text-base font-semibold text-white leading-tight text-center"   :style="style"
    >
      {{ data?.name }}
    </div>
  </div>
</template>
