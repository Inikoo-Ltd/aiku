<script setup lang="ts">
import { computed } from 'vue'
import { trans } from "laravel-vue-i18n"
import BannerPreview from '@/Components/Banners/BannerPreview.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { cloneDeep } from 'lodash-es'

const props = defineProps<{
  data: {
    id: number
    ulid: string
    state: string
    delivery_url: string
    export_url: string
    compiled_layout?: {
      components?: Array<{
        visibility: boolean
        [key: string]: any
      }>
      [key: string]: any
    }
  }
}>()

const filteredData = computed(() => {
  if (!props.data?.compiled_layout?.components) {
    return props.data
  }

  const cloned = cloneDeep(props.data)

  cloned.compiled_layout.components =
    cloned.compiled_layout.components.filter(
      (item: { visibility: boolean }) => item.visibility === true
    )

  return cloned
})
</script>

<template>
  <div class="w-full mx-auto">
    <BannerPreview
      v-if="
        filteredData?.compiled_layout?.components?.length &&
        filteredData.state !== 'switch_off'
      "
      :data="filteredData"
    />

    <EmptyState
      v-else
      :data="{
        title:
          filteredData.state !== 'switch_off'
            ? trans('You do not have slides to show')
            : trans('You turn off the banner'),
        description:
          filteredData.state !== 'switch_off'
            ? trans('Create new slides in the workshop to get started')
            : trans('need re-publish the banner at workshop'),
      }"
    />
  </div>
</template>