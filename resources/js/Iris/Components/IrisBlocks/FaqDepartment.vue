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
    <div :id="fieldValue?.id ? fieldValue?.id  : 'faq'+ indexBlock" class="w-full" component="cta1">
        <Faq :fieldValue="fieldValue" :screen-type="screenType" />
    </div>
</template>
