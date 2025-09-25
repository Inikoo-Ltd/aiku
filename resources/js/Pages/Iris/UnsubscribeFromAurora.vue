<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCheck, faPlus, faMinus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import LayoutIris from '@/Layouts/Iris.vue'


const props = defineProps<{
    webpage: any
    web_blocks: any,
    webpage_img: any,
}>()
defineOptions({ layout: LayoutIris })
library.add(faCheck, faPlus, faMinus)





</script>

<template>
    dgfdsfgdfg
</template>
