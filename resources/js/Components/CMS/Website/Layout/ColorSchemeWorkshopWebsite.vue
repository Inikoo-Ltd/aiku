<script setup lang="ts">
import { routeType } from '@/types/route'
import { getStyles } from "@/Composables/styles"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExternalLink } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faExternalLink)

const props = defineProps<{
  layout?: string
  theme?: {
    color?: string[]
    layout?: string
    fontFamily?: string
  }
  routeList: {
    headerRoute: routeType
    footerRoute: routeType
    webpageRoute: routeType
    notificationRoute: routeType
    menuLeftRoute: routeType
    menuRightRoute: routeType
    menuRoute: routeType
  }
}>()
</script>

<template>
  <div
    class="h-fit rounded-md overflow-hidden border-2 border-gray-400 flex justify-center"
  >
    <div :class="theme.layout === 'fullscreen' ? 'w-full' : 'w-[80%]'" class="transition-all">

      <!-- Notification -->
      <div class="group relative w-full h-8 bg-gray-200 text-gray-700 text-xxs flex items-center justify-center cursor-default">
        <span>Notification</span>
        <div class="inset-0 absolute items-center justify-center hidden group-hover:flex gap-x-1.5 bg-black/50 text-white text-xs whitespace-nowrap">
          <!-- <span>Open Notification workshop</span>
          <FontAwesomeIcon :icon="['fal', 'external-link']" fixed-width /> -->
          Coming soon..
        </div>
      </div>

      <!-- Header -->
      <a
        class="group relative flex items-center justify-between h-20 px-4 cursor-pointer"
        :href="routeList.headerRoute?.name ? route(routeList.headerRoute.name, routeList.headerRoute.parameters) : '#'"
        :style="{ backgroundColor: theme.color?.[0], color: theme.color?.[1] }"
        target="_blank"
      >
        <div class="bg-gray-100 text-black border border-gray-400 px-0.5 aspect-square rounded-sm flex items-center text-[12px]">Logo</div>

        <div class="flex justify-center gap-x-2">
          <div class="w-[200px] bg-white rounded-sm px-2 text-[15px] text-gray-500">Search</div>
        </div>

        <div class="text-xs flex gap-x-1.5">
          <span>Register</span>
          <span>Login</span>
        </div>

        <div class="inset-0 absolute hidden group-hover:flex items-center justify-center gap-x-1.5 bg-black/50">
          <span>Open Header workshop</span>
          <FontAwesomeIcon :icon="['fal', 'external-link']" fixed-width />
        </div>
      </a>

      <!-- Menu -->
      <a
        class="group relative flex items-center justify-between h-10 px-4 cursor-pointer"
        :href="routeList.menuRoute?.name ? route(routeList.menuRoute.name, routeList.menuRoute.parameters) : '#'"
        :style="{ backgroundColor: theme.color?.[0], color: theme.color?.[1] }"
        target="_blank"
      >
        <div class="text-black text-[8px]"></div>

        <div class="flex justify-center gap-x-2">
          <div class="relative px-1 flex justify-center">
            <span class="text-xs">Menu</span>
            <div class="absolute -bottom-0.5 h-[2px] w-full rounded-full" :style="{ backgroundColor: theme.color?.[2] }"></div>
          </div>
          <div class="relative px-1 flex justify-center">
            <span class="text-xs">Product</span>
          </div>
          <div class="relative px-1 flex justify-center">
            <span class="text-xs">Contact Us</span>
            <div class="absolute -bottom-0.5 h-[2px] w-full rounded-full" :style="{ backgroundColor: theme.color?.[2] + '33' }"></div>
          </div>
        </div>

        <div class="inset-0 absolute hidden group-hover:flex items-center justify-center gap-x-1.5 bg-black/50">
          <span>Open Menu workshop</span>
          <FontAwesomeIcon :icon="['fal', 'external-link']" fixed-width />
        </div>
      </a>

      <!-- Webpage Section -->
      <div class="flex justify-center" :style="getStyles(props.theme.container?.properties, 'desktop')">
        <a
          class="group relative col-span-5 pb-8 h-[42vh] cursor-pointer w-full"
          :href="routeList.webpageRoute?.name ? route(routeList.webpageRoute.name, routeList.webpageRoute.parameters) : '#'"
          target="_blank"
        >
          <div class="mt-6 font-black mx-auto text-center text-2xl whitespace-pre-line px-6" :style="{ color: theme.color?.[0] }">
            Lorem ipsum dolor, sit amet consectetur adipisicing elit!
          </div>

          <div class="mt-8 flex justify-center items-center scale-100">
            <!-- Basic Plan -->
            <div class="mx-4 px-4 py-4 rounded-md w-52" :style="{ color: theme.color?.[2], border: `2px solid ${theme.color?.[2]}` }">
              <div class="text-sm">Basic</div>
              <div class="text-2xl font-black">$74<span class="text-xs font-normal">/month</span></div>
              <div class="text-xs mt-2">Ideal for small businesses or startups with moderate storage needs.</div>
              <div class="mt-3 rounded w-fit px-4 text-sm" :style="{ color: theme.color?.[5], backgroundColor: theme.color?.[4] }">Buy</div>
            </div>

            <!-- Pro Plan -->
            <div class="mx-4 scale-110 px-4 py-4 rounded-md w-52" :style="{ backgroundColor: theme.color?.[2], color: theme.color?.[3] }">
              <div class="text-sm">Pro</div>
              <div class="text-2xl font-black">$279<span class="text-xs font-normal">/month</span></div>
              <div class="text-xs mt-2">Perfect for growing businesses with increased storage demands.</div>
              <div class="mt-3 text-white rounded w-fit px-4 text-sm" :style="{ backgroundColor: theme.color?.[3], color: theme.color?.[2] }">Buy</div>
            </div>
          </div>

          <div class="inset-0 absolute hidden group-hover:flex items-center justify-center gap-x-1.5 bg-black/50 text-white">
            <span>Open Webpage workshop</span>
            <FontAwesomeIcon :icon="['fal', 'external-link']" fixed-width />
          </div>
        </a>
      </div>

      <!-- Footer -->
      <a
        class="group relative flex items-center justify-between cursor-pointer"
        :href="routeList.footerRoute?.name ? route(routeList.footerRoute.name, routeList.footerRoute.parameters) : '#'"
        :style="{ backgroundColor: theme.color?.[2], color: theme.color?.[3] }"
        target="_blank"
      >
        <div class="w-full h-full grid grid-cols-4 px-6 py-3 text-xxs">
          <div class="flex flex-col gap-1">
            <span>Home</span>
            <span>About</span>
            <span>Contact us</span>
          </div>

          <div class="flex flex-col gap-1">
            <span>Terms of use</span>
            <span>Privacy Policy</span>
          </div>

          <div class="text-justify text-[8px]">
            Lorem ipsum dolor sit amet consectetur adipisicing elit. Aut, placeat!
          </div>

          <div class="flex justify-end">
            <div class="bg-gray-100 text-black border border-gray-400 px-0.5 aspect-square rounded-sm flex items-center text-[14px]">Logo</div>
          </div>
        </div>

        <div class="inset-0 absolute hidden group-hover:flex items-center justify-center gap-x-1.5 bg-black/50 text-white">
          <span>Open Footer workshop</span>
          <FontAwesomeIcon :icon="['fal', 'external-link']" fixed-width />
        </div>
      </a>
    </div>
  </div>
</template>
