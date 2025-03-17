<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, provide } from 'vue'
import { blueprint as cta_aurora_1 } from "@/Components/CMS/Webpage/CTAAurora1/Blueprint";
import { set as setLodash, get, cloneDeep } from 'lodash-es'

import { getFormValue } from '@/Composables/SideEditorHelper'
import { blueprint } from '@/Components/Workshop/BlueprintSiteSettings'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { Root as RootWebpage } from '@/types/webpageTypes'


const props = defineProps<{
    webpage: RootWebpage
}>()

const value = ref({
    button : null
})

const setChild = (blueprint = [], data = {}) => {
    const result = { ...data }
    for (const form of blueprint) {
        getFormValues(form, result)
    }
    return result
}

const getFormValues = (form: any, data: any = {}) => {
    const keyPath = Array.isArray(form.key) ? form.key : [form.key]
    if (form.editGlobalStyle) {
        setLodash(data, ['container', 'properties'], { ...value.value[form.editGlobalStyle] });
    } else if (form.replaceForm) {
        const set = getFormValue(data, keyPath) || {}
        setLodash(data, keyPath, setChild(form.replaceForm, set))
    }
}

const onSaveWorkshopFromId = (blueprint = []) => {
    for (const form of cta_aurora_1) {
        for (const web_block of props.webpage.layout.web_blocks) {
            for (const web_block of props.webpage.layout.web_blocks) {
                getFormValues(form, web_block.web_block.layout.data.fieldValue)
            }
        }
    }
    console.log('Final Data Web Block', props.webpage.layout.web_blocks)
};

provide("onSaveWorkshopFromId", onSaveWorkshopFromId);

</script>

<template>
    <SideEditor v-model="value" :blueprint="blueprint" />
</template>

<style scoped></style>