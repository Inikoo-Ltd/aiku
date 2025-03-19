<script setup lang="ts">
import ParentFieldSideEditor from '@/Components/Workshop/SideEditor/ParentFieldSideEditor.vue'
import { get } from 'lodash-es'
import Accordion from 'primevue/accordion'
import { ref } from 'vue'

import { routeType } from '@/types/route'
const props = defineProps<{
    blueprint: Array<{ key: string; label?: string; type?: string }>
    uploadImageRoute?: routeType
}>()

const modelValue = defineModel()
const openPanel = ref(0)
const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()


</script>

<template>
    <div v-for="(form, index) of blueprint.filter((item)=>item.type != 'hidden')" :key="form.key" class="">
        <Accordion v-if="form.name" class="w-full" v-model="openPanel">
            <div v-if="form.type != 'hidden'">
                <div v-if="get(form, 'label', '')" class="my-2 text-xs font-semibold">{{ get(form, 'label', '') }}</div>
                <ParentFieldSideEditor :blueprint="form" :modelValue="modelValue" :uploadImageRoute="uploadImageRoute" 
                    @update:modelValue="newValue => emits('update:modelValue', newValue)" :index="index" />
            </div>
        </Accordion>
        
        <section v-else class="">
            <ParentFieldSideEditor :blueprint="form" :modelValue="modelValue" :uploadImageRoute="uploadImageRoute" 
                @update:modelValue="newValue => emits('update:modelValue', newValue)" :index="index" />
        </section>

    </div>
</template>


<style lang="scss" scoped></style>
