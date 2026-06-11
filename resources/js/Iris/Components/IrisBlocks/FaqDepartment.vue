<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FieldValue } from "@/types/webpageTypes"
import { inject, computed } from 'vue'
import Faq from '@/Iris/Components/BlocksUtils/FamilyExtraDescription2/Faq.vue'
import { getStyles } from "@/Composables/styles"


const props = defineProps<{
    fieldValue: FieldValue
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
    indexBlock?: number | string
    code?: string
}>()

const layout: any = inject("layout", {})

const containerStyle = computed(() => ({
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
    ...getStyles(props.fieldValue?.faq?.container?.properties),
    width: "auto",
}))

</script>

<template>
    <div class="mx-auto w-full max-w-[1700px]  px-4 py-4 sm:px-8 xl:px-14 2xl:max-w-[1800px] 2xl:px-14"
        :style="containerStyle" :id="fieldValue?.id ? fieldValue?.id : 'faq' + indexBlock" component="faq-department">
        <Faq :fieldValue="fieldValue" :screen-type="screenType" :faqs="fieldValue.faqs" />
    </div>
</template>
