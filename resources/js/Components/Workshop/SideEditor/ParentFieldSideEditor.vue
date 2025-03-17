<script setup lang="ts">
import { inject, ref } from 'vue'

import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import ChildFieldSideEditor from '@/Components/Workshop/SideEditor/ChildFieldSideEditor.vue'
import { trans } from 'laravel-vue-i18n'
import { kebabCase } from 'lodash-es'
import { v4 as uuidv4 } from 'uuid';

import { getFormValue ,setFormValue, getComponent } from '@/Composables/SideEditorHelper'
import { get } from 'lodash-es'
import { routeType } from '@/types/route'
import Icon from '@/Components/Icon.vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faInfoCircle)

const props = defineProps<{
    blueprint: {
        name : String,
        type : String,
        key : string | string[],
        replaceForm : Array<any>
    }
    uploadImageRoute?: routeType,
    index:Number | string | string[]
}>()
const modelValue = defineModel()

const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') })  // Get the block id that use this property

const onPropertyUpdate = (fieldKeys: string | string[], newVal: any) => {
    setFormValue(modelValue.value, fieldKeys, newVal)
    onSaveWorkshopFromId(side_editor_block_id, 'parentfieldsideeditor')

}

</script>

<template>
    <AccordionPanel v-if="blueprint.name" :key="blueprint.name" :value="blueprint?.key?.join('-')">
        <AccordionHeader>
            <div>
                <Icon v-if="blueprint?.icon" :data="blueprint.icon" />
                {{ get(blueprint, 'name') }}
            </div>
        </AccordionHeader>
        
        <AccordionContent class="px-0">
            <div class="">
                <template v-if="blueprint.replaceForm">
                    <ChildFieldSideEditor 
                        :blueprint="blueprint.replaceForm"
                        :modelValue="getFormValue(modelValue, blueprint.key)"
                        :key="blueprint.key "
                        :uploadImageRoute="uploadImageRoute" 
                        @update:modelValue="newValue => onPropertyUpdate(blueprint.key, newValue)"
                    />
                </template>

                <template v-else >
                   <!--  <div class="my-2 text-xs font-semibold">{{ get(blueprint, 'label', '') }}</div> -->
                    <div 
                         class="w-full my-2 text-center py-1 font-semibold select-none text-sm "
                         :class="blueprint.label && 'border-b border-gray-300 py-2'"
                    >
                         {{ trans(get(blueprint, 'label', '')) }}
                    </div>
                    <component 
                        :is="getComponent(blueprint.type)" 
                         :key="blueprint.key "
                        :modelValue="getFormValue(modelValue, blueprint.key)"
                        :uploadRoutes="uploadImageRoute" 
                        v-bind="blueprint?.props_data" 
                        @update:modelValue="newValue => onPropertyUpdate(blueprint.key, newValue)"
                    />
                </template>
            </div>
        </AccordionContent>
    </AccordionPanel>

    <div v-else class="bg-white mt-[0px] mb-2  pb-3">
        <template v-if="blueprint.replaceForm">
            <ChildFieldSideEditor
                :blueprint="blueprint.replaceForm"
                :modelValue="getFormValue(modelValue, blueprint.key)"
                :key="blueprint.key "
                @update:modelValue="newValue => onPropertyUpdate(blueprint.key, newValue)"
            />
        </template>

        <template v-else>
           <!-- Section: Padding, Margin, Border -->
            <div v-if="get(blueprint, 'label', '')" class="w-full my-2 text-start py-1 font-semibold select-none text-sm border-b border-gray-300">
                {{ trans(get(blueprint, 'label', '')) }}
                <VTooltip v-if="blueprint.information" class="inline w-fit" placements="right"> <!-- This placement don't work, later change to right -->
                    <FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-500 cursor-pointer" fixed-width aria-hidden="true" />
                    <template #popper>
                        <div class="min-w-20 w-fit max-w-64 text-xs">
                            {{ blueprint.information }}
                        </div>
                    </template>
                </VTooltip>
            </div>
            <component 
                :is="getComponent(blueprint.type)" 
                :key="blueprint.key "
                :uploadRoutes="uploadImageRoute" 
                v-bind="blueprint?.props_data" 
                :modelValue="getFormValue(modelValue, blueprint.key)"
                @update:modelValue="newValue => {
                    // console.log(index, 'getfomvalue', getFormValue(modelValue, blueprint.key))
                    onPropertyUpdate(blueprint.key, newValue)
                }"
            />
        </template>
    </div>

</template>


<style lang="scss" scoped>
.editor-content {
    background-color: white;
    border: solid;
}

.p-inputtext {
    width: 100%;
}

.p-accordionpanel.p-accordionpanel-active > .p-accordionheader {
    background-color: #433CC3 !important;
    border-radius: 0 !important;
    color: #fdfdfd !important;
}

.p-accordioncontent-content {
    padding: 10px !important;
    background-color: #F9F9F9 !important;
}

</style>
