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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle} from '@fal'
import { faSparkles } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
library.add(faInfoCircle, faSparkles)

const props = defineProps<{
    blueprint: {
        name: string
        type: string
        key: string | string[]
        useIn: string[]
        replaceForm: Array<any>
        icon?: any
        show_new_until?: string  // "2025-06-04"
    }
    uploadImageRoute?: routeType
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: number): void
}>()

const layout: any = inject("layout", {})
const modelValue = defineModel()


// Check if this blueprint should use a custom form editor
const hasCustomForm = computed(() =>
    Array.isArray(props.blueprint.replaceForm) && props.blueprint.replaceForm.length > 0
)

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') }) */

const onPropertyUpdate = (fieldKeys: string | string[], newVal: any) => {
    const setValue = setFormValue(modelValue.value || {}, fieldKeys, newVal)
    // console.log('value from set ', setValue)
    emits('update:modelValue', setValue);
    /* onSaveWorkshopFromId(side_editor_block_id, 'parentfieldsideeditor') */
}

const accordionKey = computed(() => {
    if (Array.isArray(props.blueprint.key)) {
        return props.blueprint.key.join('.')
    }

    return props.blueprint.key
})

// Method: Check if the future date has passed
const isFutureDatePassed = (futureDate: string) => {
    const today = new Date();
    const targetDate = new Date(futureDate);

    today.setHours(0, 0, 0, 0);
    targetDate.setHours(0, 0, 0, 0);

    return targetDate < today;
}
</script>

<template>
    <!-- Accordion mode -->
    <AccordionPanel v-if="blueprint.name" :key="accordionKey" :value="blueprint.accordion_key ?? accordionKey">
        <AccordionHeader>
            <div class="flex items-center gap-2">
                <Icon v-if="blueprint.icon" :data="blueprint.icon" />
                <div>
                    <span>{{ blueprint.name }}</span>
                    <!-- Section: 'New' label -->
                    <div v-if="blueprint.show_new_until && !isFutureDatePassed(blueprint.show_new_until)"
                        class="ml-2 inline bg-yellow-100 border border-yellow-300 text-yellow-600 whitespace-nowrap items-center gap-x-1 rounded select-none pl-0.5 pr-1 py-0.5 text-xs w-fit font-medium"
                    >
                        <FontAwesomeIcon icon="fas fa-sparkles" class="" fixed-width aria-hidden="true" />
                        {{ trans("New") }}
                    </div>
                </div>
            </div>
        </AccordionHeader>

        <AccordionContent class="p-4">
            <ChildFieldSideEditor v-if="hasCustomForm" :modelValue="getFormValue(modelValue, blueprint.key)"
                :blueprint="blueprint" :uploadImageRoute="uploadImageRoute" @update:model-value="(e)=>onPropertyUpdate(blueprint.key,e)" />

            <RenderFields v-else :modelValue="modelValue" :blueprint="blueprint"
                :uploadImageRoute="uploadImageRoute"
                @update:modelValue="onPropertyUpdate" />
        </AccordionContent>
    </AccordionPanel>

    <!-- Non-accordion mode -->
    <div v-else class="bg-white mt-0 mb-2 pb-3">
        <ChildFieldSideEditor v-if="hasCustomForm" :modelValue="getFormValue(modelValue, blueprint.key)"
            :blueprint="blueprint" :uploadImageRoute="uploadImageRoute"  @update:model-value="(e)=>onPropertyUpdate(blueprint.key,e)" />


        <RenderFields v-else :modelValue="modelValue" :blueprint="blueprint"
            :uploadImageRoute="uploadImageRoute"
            @update:modelValue="onPropertyUpdate" />
    </div>
</template>

<style scoped lang="scss">
/* Override PrimeVue accordion active panel header */
.p-accordionpanel.p-accordionpanel-active > .p-accordionheader {
  background-color:  v-bind('layout?.app?.theme[4]') !important;
  border-radius: 0 !important;
  color: white !important;
  font-weight: 600;
  transition: background-color 0.2s ease-in-out;
}

/* Accordion content styling */
.p-accordioncontent-content {
  padding: 1rem !important;
  background-color: #f9f9f9 !important;
  border-top: 1px solid #e5e7eb;
}

/* Input width standardization */
.p-inputtext {
  width: 100%;
}

/* Optional: Add smooth transition to open/close */
.p-accordionpanel {
  transition: all 0.2s ease-in-out;
}

/* Section 'New' badge (optional refinement) */
.new-badge {
  background-color: #fef9c3;
  border: 1px solid #fde68a;
  color: #b45309;
  font-weight: 500;
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  border-radius: 0.25rem;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}
</style>
