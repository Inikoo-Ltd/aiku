<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FieldValue } from "@/types/webpageTypes"
import { inject } from 'vue'
import Faq from '@/Iris/Components/BlocksUtils/FamilyExtraDescription2/Faq.vue'



const props = defineProps<{
    fieldValue: FieldValue
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
    indexBlock?: number | string
    code?: string
}>()

const layout: any = inject("layout", {})



</script>

<template>
  <div
    v-if="fieldValue?.faqs?.length"
    :id="fieldValue?.id ? fieldValue?.id : 'faq' + indexBlock"
    component="faq-department"
    class="bg-[#F3F3F3]"
  >
    <div class="mx-auto max-w-[1600px] px-6 py-12 lg:px-12">
      <h2
        class="mb-8 text-4xl font-bold tracking-tight text-[#0F1E2E] lg:text-5xl"
      >
        FAQ
      </h2>

      <Faq
        :fieldValue="fieldValue"
        :screen-type="screenType"
        :faqs="fieldValue.faqs"
      />
    </div>
  </div>
</template>
