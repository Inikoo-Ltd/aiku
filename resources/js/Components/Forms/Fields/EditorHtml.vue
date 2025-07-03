<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import codemirrorPkg from 'vue-codemirror';
import { javascript } from "@codemirror/lang-javascript"
import { css } from "@codemirror/lang-css"
import { html } from "@codemirror/lang-html"
import { EditorView } from "@codemirror/view"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    refForms?: any
    fieldData?: {
        country: Array<string>
    }
}>()

const { Codemirror } = codemirrorPkg;
const emits = defineEmits()


</script>

<template>
    <div class="border">
        <Codemirror v-model="form[fieldName]" :style="{ height: '300px', textOverflow: 'ellipsis' }" :autofocus="true"
            :indent-with-tab="true" :tab-size="2" :extensions="extensions" @ready="handleReady" />
    </div>

</template>
