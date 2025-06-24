<script setup lang="ts">
import { onMounted, provide } from 'vue'

import Accordion from 'primevue/accordion'
import ParentFieldSideEditor from '@/Components/Workshop/SideEditor/ParentFieldSideEditor.vue'

import { getFormValue } from '@/Composables/SideEditorHelper'
import { set as setLodash, get } from 'lodash-es'

import { routeType } from '@/types/route'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCaretDown, faCaretLeft} from '@fas'

const props = withDefaults(defineProps<{
    blueprint: {
        name?: string
        key?: string | string[]
        replaceForm?: {
            name?: string
            key?: string | string[]
            props_data?: {
                defaultValue?: string | number | null
            }
            type?: string
            options?: {
                label: string
                value: string
            }
        }[]
    }[]
    uploadImageRoute?: routeType
    block?: {
        id: number
    }
    panelOpen?: number|null
}>(), {
    panelOpen: null,
})

const modelValue = defineModel()

provide('side_editor_block_id', props.block?.id)


const emits = defineEmits<{
    (e: 'update:modelValue', value: {}): void
}>()

const setChild = (blueprint = [], data = {}) => {
    const result = { ...data }
    for (const form of blueprint) {
        getFormValues(form, result)
    }
    return result
}

const getFormValues = (form: any, data: any = {}) => {
    const keyPath = Array.isArray(form.key) ? form.key : [form.key] 
    if (form.replaceForm) {
        const set = getFormValue(data, keyPath) || {}
        setLodash(data, keyPath, setChild(form.replaceForm, set))
    } else {
        if (!get(data, keyPath)) {
            setLodash(data, keyPath, get(form, ["props_data", 'defaultValue'], null))
        }
    }
}

const setFormValues = (blueprint = [], data = {}) => {
    for (const form of blueprint) {
        getFormValues(form, data)
    }

    return data
}

onMounted(() => {
    if(!modelValue.value){
        emits('update:modelValue', setFormValues(props.blueprint))
    }
})


</script>

<template>
    <div v-for="(field, index) of blueprint.filter((item) => item.type != 'hidden')">
        <Accordion class="w-full" :value="panelOpen">
            <template #collapseicon>
                <FontAwesomeIcon :icon="faCaretDown" class="text-white"></FontAwesomeIcon>
            </template>
            <template #expandicon>
                <FontAwesomeIcon :icon="faCaretLeft" class="text-black"></FontAwesomeIcon>
            </template>
            
            <ParentFieldSideEditor 
                :blueprint="field" 
                :uploadImageRoute="uploadImageRoute" 
                 v-model="modelValue"
                :key="field.key"
                @update:modelValue="e =>  emits('update:modelValue', e)"
            />
        </Accordion>
    </div>
</template>


<style lang="scss" scoped>
::v-deep(.p-accordioncontent ) {
  padding: 0px 0px 0px 0px !important;
}
::v-deep(.p-accordioncontent-content) {
  padding: 1rem !important;
}
</style>
