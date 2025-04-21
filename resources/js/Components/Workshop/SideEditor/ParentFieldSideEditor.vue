<script setup lang="ts">
import { computed, inject } from 'vue'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'

import Icon from '@/Components/Icon.vue'
import RenderFields from './RenderFields.vue'
import ChildFieldSideEditor from '@/Components/Workshop/SideEditor/ChildFieldSideEditor.vue'
import { getFormValue, setFormValue } from '@/Composables/SideEditorHelper'
import { routeType } from '@/types/route'

// FontAwesome setup
import { faInfoCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faInfoCircle)

const props = defineProps<{
    blueprint: {
        name: string
        type: string
        key: string | string[]
        useIn: string[]
        replaceForm: Array<any>
        icon?: any
    }
    uploadImageRoute?: routeType
    index: number | string | string[]
}>()

const modelValue = defineModel()

// Check if this blueprint should use a custom form editor
const hasCustomForm = computed(() =>
    Array.isArray(props.blueprint.replaceForm) && props.blueprint.replaceForm.length > 0
)

const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') })

const onPropertyUpdate = (fieldKeys: string | string[], newVal: any) => {
    console.log('onPropertyUpdate', fieldKeys, newVal)
    setFormValue(modelValue.value, fieldKeys, newVal)
    onSaveWorkshopFromId(side_editor_block_id, 'parentfieldsideeditor')
}


</script>

<template>
    <!-- Accordion mode -->
    <AccordionPanel v-if="blueprint.name" :key="accordionKey" :value="accordionKey">
        <AccordionHeader>
            <div class="flex items-center gap-2">
                <Icon v-if="blueprint.icon" :data="blueprint.icon" />
                <span>{{ blueprint.name }}</span>
            </div>
        </AccordionHeader>

        <AccordionContent class="p-4">
            <ChildFieldSideEditor v-if="hasCustomForm" :modelValue="getFormValue(modelValue, blueprint.key)"
                :blueprint="blueprint" :uploadImageRoute="uploadImageRoute" />

            <RenderFields v-else :modelValue="modelValue" :blueprint="blueprint"
                :uploadImageRoute="uploadImageRoute"
                @update:modelValue="onPropertyUpdate" />
        </AccordionContent>
    </AccordionPanel>

    <!-- Non-accordion mode -->
    <div v-else class="bg-white mt-0 mb-2 pb-3">
        <ChildFieldSideEditor v-if="hasCustomForm" :modelValue="getFormValue(modelValue, blueprint.key)"
            :blueprint="blueprint" :uploadImageRoute="uploadImageRoute" />


        <RenderFields v-else :modelValue="modelValue" :blueprint="blueprint"
            :uploadImageRoute="uploadImageRoute"
            @update:modelValue="onPropertyUpdate" />
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

.p-accordionpanel.p-accordionpanel-active>.p-accordionheader {
    background-color: #433cc3 !important;
    border-radius: 0 !important;
    color: #fdfdfd !important;
}

.p-accordioncontent-content {
    padding: 10px !important;
    background-color: #f9f9f9 !important;
}
</style>
