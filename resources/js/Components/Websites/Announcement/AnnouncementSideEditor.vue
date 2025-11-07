<!--
* Author: Vika Aqordi
* Created on: 2025-10-03 08:11
* Github: https://github.com/aqordeon
* Copyright: 2025
-->
<script setup lang="ts">
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, onMounted } from 'vue'
import { faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faChevronDown, faCaretDown, faCaretLeft } from '@fal'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { routeType } from '@/types/route'

library.add(faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faChevronDown)

const props = defineProps<{
    blueprint?: {
        name: string
        icon: string
        replaceForm: {
            key: string
            type: string  // 'properties' || 'text' || 'background'
        }[]
    }[]
    uploadImageRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'onMounted'): void
    (e: 'update:modelValue'): void
}>()

const openFieldWorkshop = inject('openFieldWorkshop')
const announcementData = inject('announcementData', {})

onMounted(() => {
    emits('onMounted')
})

</script>

<template>
    <SideEditor
        v-model="announcementData"
        :panelOpen="openFieldWorkshop"
        :blueprint="blueprint ?? []"
        xblock="webpage.layout.web_blocks[openedBlockSideEditor]"
        xupdate:modelValue="() => sendBlockUpdate(webpage.layout.web_blocks[openedBlockSideEditor])"
        :uploadImageRoute
    />

    <!-- <Accordion :value="openFieldWorkshop" @update:value="(e) => openFieldWorkshop = e">
        <AccordionPanel v-for="(bprint, index) in blueprint" :key="index" :value="index">
            <AccordionHeader>
                <div>
                    <Icon v-if="bprint.icon" :data="bprint.icon" />
                    {{ get(bprint, 'name', 'No name') }}
                </div>
            </AccordionHeader>
            <AccordionContent class="px-0">
                <div class="">
                    <div v-for="(form, index) of bprint.replaceForm.filter((item)=>item.type != 'hidden')" :key="form.key" class="">
                        <component 
                            :is="getComponent(form.type)" 
                            :key="form.key"
                            :modelValue="getFormValue(announcementData, form.key)"
                            v-bind="form?.props_data" 
                            @update:modelValue="newValue => emits('update:modelValue', setFormValue(modelValue, form.key, newValue))"
                        />

                    </div>
                </div>
            </AccordionContent>
        </AccordionPanel>
    </Accordion> -->

</template>