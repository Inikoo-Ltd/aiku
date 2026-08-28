<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
/**
 * Units come off the composition, so most products have nothing to decide here and the block
 * stays a single line of text. It opens only when the composition cannot imply the units, or
 * when somebody says the implied ones are wrong.
 */
import { ref } from "vue"
import { InputNumber } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot, faSave as falSave } from "@fal"
import { faSave as fadSave, faSpinnerThird } from "@fad"
import { trans } from "laravel-vue-i18n"

library.add(faRobot, falSave, fadSave, faSpinnerThird)

const props = defineProps<{
    form: any
    fieldName: string
    submit?: Function
    fieldData?: {
        canToggle?: boolean
    }
}>()

const isOpen = ref(!props.fieldData?.canToggle || props.form.has_independent_units)

const setByHand = () => {
    props.form.has_independent_units = true
    isOpen.value = true
}

const backToAuto = () => {
    props.form.has_independent_units = false
    isOpen.value = false
}
</script>

<template>
    <div class="relative">
        <button
            v-if="!isOpen"
            type="button"
            @click="setByHand"
            class="group inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700"
        >
            <FontAwesomeIcon icon="fal fa-robot" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ trans("Units auto assigned") }}: {{ form[fieldName] }}
            <span class="text-gray-400 group-hover:text-indigo-600 underline underline-offset-2">
                {{ trans("click to change") }}
            </span>
        </button>

        <div v-else class="flex items-center gap-3">
            <InputNumber v-model="form[fieldName]" @input="(e) => (form[fieldName] = e.value)" showButtons />

            <button
                class="h-9 align-bottom text-center"
                :disabled="form.processing || !form.isDirty"
                type="submit"
            >
                <template v-if="form.isDirty">
                    <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third" class="text-2xl animate-spin" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                </template>
                <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
            </button>

            <button
                v-if="fieldData?.canToggle"
                type="button"
                @click="backToAuto"
                class="text-xs text-gray-400 hover:text-indigo-600 underline underline-offset-2"
            >
                {{ trans("use the composition") }}
            </button>
        </div>
    </div>
</template>
