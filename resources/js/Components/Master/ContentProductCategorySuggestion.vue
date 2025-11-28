<script setup lang="ts">
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'

const props = withDefaults(defineProps<{
    data: {
        title?: string
        description?: string
        description_extra?: string
    }
    isMaster?: boolean
}>(), {
    isMaster: false,
})
</script>

<template>
  <div class="relative grid grid-cols-2">

    <!-- FULL Vertical Divider (no gap) -->
    <div class="absolute inset-y-0 left-1/2 w-px bg-gray-300"></div>

    <!-- Column 1: Master -->
    <div class="p-6 ">
      <div class="mb-4">
        <div class="font-semibold text-blue-600 text-lg">Master</div>
        <div class="h-[2px] bg-blue-200 mt-1"></div>
      </div>

      <div class="space-y-4 pb-4">
        <div>
          <h3 class="font-semibold text-gray-800">{{ trans('Name') }}</h3>
          <p class="text-sm text-gray-600 whitespace-pre-line">{{data.name }}</p>
        </div>

        <div>
          <h3 class="font-semibold text-gray-800">{{ trans('Description') }}</h3>
          <p class="text-sm text-gray-600 whitespace-pre-line" v-html="data.description"></p>
        </div>

        <div>
          <h3 class="font-semibold text-gray-800">{{ trans('Description extra') }}</h3>
          <p class="text-sm text-gray-600 whitespace-pre-line" v-html="data.description_extra"></p>
        </div>
      </div>
    </div>

    <!-- Column 2: ShopToBe -->
    <div class="p-6">
      <div class="mb-4">
        <div class="font-semibold text-green-600 text-lg">Shop</div>
        <div class="h-[2px] bg-green-200 mt-1"></div>
      </div>

      <div class="space-y-4 pb-4">
        <div>
          <h3 class="font-semibold text-gray-800">Title</h3>
          <p class="text-sm text-gray-600 whitespace-pre-line">sdfkljsdf</p>
        </div>

        <div>
          <h3 class="font-semibold text-gray-800">Description</h3>
          <p class="text-sm text-gray-600 whitespace-pre-line">sdfjk</p>
        </div>

        <div>
          <h3 class="font-semibold text-gray-800">Description Extra</h3>
          <p class="text-sm text-gray-600 whitespace-pre-line">sdkfj</p>
        </div>
      </div>
    </div>

  </div>

  <pre>{{ data }}</pre>
</template>




<style lang="scss">
</style>
