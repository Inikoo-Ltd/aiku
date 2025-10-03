<!--
  * Author: Vika Aqordi
  * Created on: 18-02-2025-09h-16m
  * Github: https://github.com/aqordeon
  * Copyright: 2025
-->
<script setup lang="ts">
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, onMounted, ref } from 'vue'
import { faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faChevronDown } from '@fal'
// import draggable from "vuedraggable"
import PanelProperties from '@/Components/Workshop/PanelProperties.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { debounce, get } from 'lodash'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from "@/Components/Utils/Modal.vue"

// import { Root, Daum } from '@/types/webBlockTypes'
// import { Root as RootWebpage } from '@/types/webpageTypes'
import { Collapse } from 'vue-collapsed'
import { trans } from 'laravel-vue-i18n'
import Editor from '@/Components/Editor/Editor.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { propertiesToHTMLStyle } from '@/Composables/usePropertyWorkshop'
import Moveable from "vue3-moveable"
import ColorPicker from '@/Components/Utils/ColorPicker.vue'

import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import { getComponentProperties, getFormValue, setFormValue } from '@/Composables/useWorkshop';
import Icon from '@/Components/Icon.vue';
import Accordion from 'primevue/accordion';

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
}>()

const emits = defineEmits<{
    (e: 'onMounted'): void
}>()

const selectedBlockOpenPanel = ref<string | null>('content')
const isOnDrag = ref(false)
const openFieldWorkshop = inject('openFieldWorkshop')

const announcementData = inject('announcementData', {})

// const _parentOfButtonClose = ref<Element | null>(null)
// const _buttonClose = ref<Element | null>(null)
// const onDrag = (e, block_properties) => {
//     const parentWidth = _parentOfButtonClose.value?.clientWidth || 0
//     const parentHeight = _parentOfButtonClose.value?.clientHeight || 0

//     const percentageLeft = e.left / parentWidth * 100
//     const percentageTop = e.top / parentHeight * 100

//     // Update position based on the dragging
//     block_properties.position.x = `${percentageLeft}%`
//     block_properties.position.y = `${percentageTop}%`
// }

// const toVerticalCenter = (block_properties: {}) => {
//     block_properties.position.y = '50%'
// }
// const toHorizontalCenter = (block_properties: {}) => {
//     block_properties.position.x = '50%'
// }

// const debounceSetIsOnDrag = debounce(() => isOnDrag.value = false, 50)

onMounted(() => {
    emits('onMounted')
})

</script>

<template>

    <Accordion :value="openFieldWorkshop" @update:value="(e) => openFieldWorkshop = e">
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
                            :is="getComponentProperties(form.type)" 
                            :key="form.key"
                            :modelValue="getFormValue(announcementData, form.key)"
                            v-bind="form?.props_data" 
                            @update:modelValue="newValue => emits('update:modelValue',setFormValue(modelValue, form.key, newValue))"
                        />

                    </div>
                </div>
            </AccordionContent>
        </AccordionPanel>
    </Accordion>

</template>