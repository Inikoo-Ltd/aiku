<script setup lang="ts">
import { inject, nextTick, onMounted, onUnmounted, provide, ref, Ref, watch } from 'vue'

import Accordion from 'primevue/accordion'
import ParentFieldSideEditor from '@/Components/Workshop/SideEditor/ParentFieldSideEditor.vue'

import { getFormValue, getFieldKey } from '@/Composables/SideEditorHelper'
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
    panelOpen?: number|null|string
    modelType?: string // 'edit'|'filter'
    editable?:boolean
}>(), {
    panelOpen: null,
    modelType: 'edit',
    editable : true
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

const PANEL_TOGGLE_ANIMATION_MS = 250
const PANEL_HIGHLIGHT_MS = 1200

const layout: any = inject('layout', {})
const focusRequest = inject<Ref<number> | null>('childSideEditorFocusRequest', null)
const _sideEditor = ref<HTMLElement | null>(null)
const openedPanels = ref<Record<number, number | string | null>>({})
let scrollTimeout: ReturnType<typeof setTimeout> | null = null
let highlightTimeout: ReturnType<typeof setTimeout> | null = null

const openRequestedPanel = () => {
    props.blueprint.forEach((_, index) => {
        openedPanels.value[index] = props.panelOpen
    })
}

const highlightPanel = (panel: HTMLElement) => {
    panel.classList.add('side-editor-panel-focused')
    if (highlightTimeout) clearTimeout(highlightTimeout)
    highlightTimeout = setTimeout(() => panel.classList.remove('side-editor-panel-focused'), PANEL_HIGHLIGHT_MS)
}

const hasRequestedPanel = () => props.panelOpen !== null && props.panelOpen !== undefined

const scrollToRequestedPanel = async () => {
    if (!focusRequest || !hasRequestedPanel()) return

    await nextTick()
    if (scrollTimeout) clearTimeout(scrollTimeout)
    scrollTimeout = setTimeout(() => {
        const panel = _sideEditor.value?.querySelector<HTMLElement>(
            `[data-side-editor-panel="${CSS.escape(String(props.panelOpen))}"]`
        )
        if (!panel) return

        panel.scrollIntoView({ behavior: 'smooth', block: 'start' })
        highlightPanel(panel)
    }, PANEL_TOGGLE_ANIMATION_MS)
}

openRequestedPanel()

watch(() => props.block?.id, openRequestedPanel)

watch(() => props.panelOpen, () => {
    openRequestedPanel()
    scrollToRequestedPanel()
})

watch(() => focusRequest?.value, () => {
    if (!hasRequestedPanel()) return
    openRequestedPanel()
    scrollToRequestedPanel()
})

onMounted(() => {
    if(!modelValue.value){
        emits('update:modelValue', setFormValues(props.blueprint))
    }
    scrollToRequestedPanel()
})

onUnmounted(() => {
    if (scrollTimeout) clearTimeout(scrollTimeout)
    if (highlightTimeout) clearTimeout(highlightTimeout)
})


</script>

<template>
    <div ref="_sideEditor" class="w-full min-w-0 max-w-full">
        <div v-for="(field, index) of blueprint.filter((item) => item.type != 'hidden')" :key="getFieldKey(field.key, index)" class="min-w-0 max-w-full">
            <Accordion class="w-full min-w-0" v-model:value="openedPanels[index]">
                <template #collapseicon>
                    <FontAwesomeIcon :icon="faCaretDown" class="text-white" fixed-width></FontAwesomeIcon>
                </template>
                <template #expandicon>
                    <FontAwesomeIcon :icon="faCaretLeft" class="text-black" fixed-width></FontAwesomeIcon>
                </template>

                <ParentFieldSideEditor
                    :blueprint="field"
                    :uploadImageRoute="uploadImageRoute"
                    v-model="modelValue"
                    @update:modelValue="e =>  emits('update:modelValue', e)"
                    :modelType="type"
                    :editable="editable"
                />
            </Accordion>
        </div>
    </div>
</template>


<style lang="scss" scoped>
:deep(.side-editor-panel-focused) {
  animation: side-editor-panel-focus 1.2s ease-out;
}

@keyframes side-editor-panel-focus {
  0%, 40% {
    box-shadow: inset 0 0 0 2px v-bind('layout?.app?.theme?.[4] ?? "#6366f1"');
  }
  100% {
    box-shadow: inset 0 0 0 2px transparent;
  }
}

:deep(.p-accordioncontent ) {
  padding: 0px 0px 0px 0px !important;
}
:deep(.p-accordioncontent-content) {
  padding: 1rem !important;
}
:deep(.p-accordionpanel),
:deep(.p-accordionheader),
:deep(.p-accordioncontent),
:deep(.p-accordioncontent-wrapper),
:deep(.p-accordioncontent-content) {
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}
</style>
