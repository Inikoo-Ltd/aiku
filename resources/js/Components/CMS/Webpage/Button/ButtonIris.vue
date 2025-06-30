<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { FieldValue } from "@/types/webpageTypes"
import { faCube, faLink, faImage } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { inject } from "vue"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    fieldValue: FieldValue
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})
</script>

<template>
    <div id="button">
        <div class="flex m-4" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(fieldValue.container?.properties, screenType)
        }">
            <a typeof="button" :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target"
                :style="getStyles(fieldValue?.button?.container?.properties, screenType)">
                <Button :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
                    :label="fieldValue?.button?.text" />
            </a>
        </div>
    </div>
</template>