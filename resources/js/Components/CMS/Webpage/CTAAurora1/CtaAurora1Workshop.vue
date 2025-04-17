      <!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles";
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/CTAAurora1/Blueprint"

library.add(faCube, faLink)

const props = defineProps<{
    modelValue: any
	webpageData?: any
	blockData?: Object
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
    (e: 'autoSave'): void
}>()
</script>

<template>
    <div :style="getStyles(modelValue.container.properties)">
        <div class="w-full">
            <div class="relative isolate px-6 py-16 md:py-24 text-center  sm:px-16">
                <Editor  
                    v-model="modelValue.title"
                    @update:modelValue="() => emits('autoSave')"
                    :uploadImageRoute="{
                        name: webpageData.images_upload_route.name,
                        parameters: {
                            ...webpageData.images_upload_route.parameters,
                            modelHasWebBlocks: blockData?.id,
                        }
                    }"
                />
                
                <Editor  
                    v-model="modelValue.text" 
                    @update:modelValue="() => emits('autoSave')" 
                    :uploadImageRoute="{
                        name: webpageData.images_upload_route.name,
                        parameters: {
                            ...webpageData.images_upload_route.parameters,
                            modelHasWebBlocks: blockData?.id,
                        }
                    }"
                />

                <div class="flex justify-center">
                    <div @click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[1]?.key?.join('-'))" typeof="button" :style="getStyles(modelValue.button.container.properties)"
                        class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
                        {{ modelValue.button.text }}
                     </div>
                </div>
            </div>
        </div>
    </div>
</template>
