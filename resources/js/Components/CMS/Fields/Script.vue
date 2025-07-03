<script setup lang="ts">
import { ref, shallowRef, watch } from "vue"
import codemirrorPkg from 'vue-codemirror';
import { javascript } from "@codemirror/lang-javascript"
import { css } from "@codemirror/lang-css"
import { html } from "@codemirror/lang-html"
import { EditorView } from "@codemirror/view"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

const props = defineProps<{
    uploadRoutes: routeType
}>()

const model = defineModel<string>()
const { Codemirror } = codemirrorPkg;

const extensions = [javascript(), css(), html()]

// const view = shallowRef<EditorView>()

const handleReady = (payload: { view: EditorView }) => {
    // console.log('payload', payload)
    // view.value = payload.view
}

// Apply code and emit updated modelValue
const applyCode = () => {
    // model.value = code.value
    //   emit("update:modelValue", code.value)
}

</script>

<template>
    <div class="overflow-ellipsis max-w-72 overflow-hidden">
        <!-- <div class="flex justify-end mb-3">
            <Button label="Apply" type="save" size="xs" @click="applyCode" />
        </div> -->

        <Codemirror
            v-model="model"
            placeholder="<script> console.log('Hello, World!') </script>"
            :style="{ height: '300px', textOverflow: 'ellipsis' }"
            :autofocus="true"
            :indent-with-tab="true"
            :tab-size="2"
            :extensions="extensions"
            @ready="handleReady"
        />
    </div>
</template>

<style scoped lang="scss">
.codemirror {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    max-width: 100%;
}

// :deep(.cm-line) {
//     overflow: hidden;
//     text-overflow: ellipsis !important
// }
</style>
