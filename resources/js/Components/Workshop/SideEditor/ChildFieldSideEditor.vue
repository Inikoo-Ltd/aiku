<script setup lang="ts">
import ParentFieldSideEditor from '@/Components/Workshop/SideEditor/ParentFieldSideEditor.vue'
import Accordion from 'primevue/accordion'
import { ref } from 'vue'

import { routeType } from '@/types/route'
const props = defineProps<{
    blueprint: {
        replaceForm : Array<any>
    }
    uploadImageRoute?: routeType
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: number): void
}>()
const modelValue = defineModel()
const openPanel = ref(0)

</script>

<template>
    <div v-for="(form, index) in blueprint.replaceForm.filter(f => f.type !== 'hidden')" :key="form.key">
        <Accordion v-if="form.name" class="w-full" v-model="openPanel">
            <template #default>
                <div v-if="form.label" class="my-2 text-xs font-semibold">{{ form.label }}</div>
                <ParentFieldSideEditor 
                    :blueprint="form" 
                    :modelValue="modelValue"
                    :uploadImageRoute="uploadImageRoute" 
                    @update:modelValue="e =>   emits('update:modelValue', e)"
                />
            </template>
        </Accordion>

        <div v-else>
            <ParentFieldSideEditor 
                :blueprint="form" 
                :modelValue="modelValue"
                :uploadImageRoute="uploadImageRoute" 
                @update:modelValue="e =>  emits('update:modelValue', e)"
            />
        </div>
    </div>

</template>


<style lang="scss" scoped></style>
