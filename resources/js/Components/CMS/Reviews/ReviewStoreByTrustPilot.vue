<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, onMounted, onBeforeUnmount, computed } from 'vue'

const props = defineProps<{
    review: any
    code: string
}>()


const layout: any = inject("layout", {})

const ReviewIframeUrl = computed(() => {
  const config = props.review
  const provider = config?.provider

  if (provider === 'trust_pilot') {
    const data = config?.data
    if (!data?.template_id || !data?.business_unit_id) return null

    const locale = layout.iris.locale || 'en-us'

    return `https://widget.trustpilot.com/trustboxes/${data.template_id}/index.html?businessunitId=${data.business_unit_id}&locale=${locale}`
  }

  return null
})



</script>

<template>
          <iframe
            v-if="ReviewIframeUrl"
            :src="ReviewIframeUrl"
            class="w-full border-0 h-[400px] md:h-[200px] lg:h-[200px]"
            frameborder="0"
            scrolling="no"
            title="Customer Reviews"
            loading="lazy"
          />
</template>
