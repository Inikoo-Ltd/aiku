<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue";
import { Button as ButtonTS } from "@/types/Button";
import { routeType } from "@/types/route";
import { Link } from "@inertiajs/vue3";
import { ctrans } from "@/Composables/useTrans"

defineProps<{
  error: {
    code: number
    title: string
    description: string
  },
  datetime: string
  routeName: string
  navigations: {
    route: routeType
    button: ButtonTS
  }[]
}>();


</script>

<template>
  <main class="grid min-h-full place-items-center bg-white px-6 py-24 sm:py-32 lg:px-8">
    <div class="text-center">
      <p class="text-6xl font-semibold text-indigo-600">{{ error.status || 404 }}</p>
      <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-5xl">{{ error.title || ctrans("Page not found") }}</h1>
      <p class="mt-6 text-base leading-7 text-gray-600">{{ error.description || ctrans("Sorry, we could not find the page you’re looking for.") }}</p>

      <p class="mt-12 text-sm leading-7 text-gray-600 border border-gray-300 p-2 rounded-md font-mono">{{routeName}}<br/>{{ datetime }}</p>
      <div class="mt-10 flex items-center justify-center gap-x-6">
        <component v-for="nav in navigations"
                   :is="nav.route?.name ? Link : 'div'"
                   :href="nav.route?.name ? route(nav.route?.name, nav.route?.parameters) : '#'"
        >
          <Button v-bind="nav.button" />
        </component>
      </div>
    </div>
  </main>
</template>