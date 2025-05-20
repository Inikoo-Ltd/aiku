<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FieldValue } from "@/types/webpageTypes"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faLink, faImage } from "@fal"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    fieldValue: FieldValue
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()


</script>

<template>
    <div class="flex" :style="getStyles(fieldValue?.container?.properties,screenType)">
        <a typeof="button" :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target"
            :style="getStyles(fieldValue?.button?.container?.properties,screenType)" class="flex items-center justify-center">
            {{ fieldValue?.button?.text }}
        </a>
    </div>
</template>