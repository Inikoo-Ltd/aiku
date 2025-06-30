<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from "vue"
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faCube, faLink)

const props = defineProps<{
    fieldValue: {
        title: string,
        text: string
        button: {
            container: {
                properties: object | null
            }
        }
    }
    webpageData?: any
    screenType: "mobile" | "tablet" | "desktop"
    blockData?: Object
}>()

const layout: any = inject("layout", {})
</script>

<template>
    <div>
 <div :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType)
    }">
        <div class="w-full">
            <div class="relative  px-6 py-16 md:py-24 text-center sm:px-16">
                <section v-html="fieldValue.title"></section>
                <section v-html="fieldValue.text"></section>
                <div class="flex justify-center">
                    <a :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target"
                        typeof="button">
                        <Button :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
                            :label="fieldValue?.button?.text" />
                    </a>
                </div>
            </div>
        </div>
    </div>
    </div>
   
</template>
