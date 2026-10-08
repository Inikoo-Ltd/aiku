<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Oct 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import InputText from "primevue/inputtext"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    fieldData: {
        terms: string[]
        language: string
    }
}>()

if (!props.form[props.fieldName] || Array.isArray(props.form[props.fieldName])) {
    props.form[props.fieldName] = {}
}
</script>

<template>
    <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] items-center gap-x-4 gap-y-2 max-w-2xl">
        <div class="text-xs font-medium text-gray-500">{{ ctrans("English") }}</div>
        <div class="text-xs font-medium text-gray-500">{{ fieldData.language }}</div>
        <template v-for="term in fieldData.terms" :key="term">
            <label :for="`${fieldName}-${term}`" class="text-sm text-gray-700">{{ term }}</label>
            <InputText
                :id="`${fieldName}-${term}`"
                v-model="form[fieldName][term]"
                :placeholder="term"
                size="small"
                class="w-full"
                @update:modelValue="() => form.errors[fieldName] = null"
            />
        </template>
    </div>
</template>
