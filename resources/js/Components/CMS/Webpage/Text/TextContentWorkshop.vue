<script setup lang="ts">
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from '@/Composables/styles'
import { inject, computed, watch, ref } from "vue"
import Blueprint from "./Blueprint"
import { sendMessageToParent } from "@/Composables/Workshop"
import { get, isPlainObject, cloneDeep, set } from 'lodash-es'

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  indexBlock?: number
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
  (e: 'autoSave'): void
}>()

const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
const layout: any = inject("layout", {})
const key = ref(1)

const valueForField = computed({
  get() {
    const rawVal = get(props.modelValue, ['value'])
    if (!isPlainObject(rawVal)) return rawVal

    if (!rawVal?.use_responsive) {
      return rawVal?.desktop ?? ''
    }

    const view = props.screenType!
    return rawVal?.[view] ?? rawVal?.desktop ?? ''
  },
  set(newVal) {
    const rawVal = cloneDeep(get(props.modelValue, ['value']))

    if (!isPlainObject(rawVal)) {
      set(props.modelValue, ['value'], newVal)
      emits('autoSave')
      return
    }
    if (!rawVal?.use_responsive) {
      set(props.modelValue, ['value', 'desktop'], newVal)
    } else {
      set(props.modelValue, ['value', props.screenType], newVal)
    }

    emits('autoSave')
  }
})

watch(() => props.modelValue.value, () => {
  key.value++
})
</script>

<template>
  <div id="text">
    <div
      @click="() => {
        sendMessageToParent('activeBlock', indexBlock)
        sendMessageToParent('activeChildBlock', bKeys[1])
      }"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType)
      }"
    >
      <EditorV2
        v-model="valueForField"
        :key="screenType + '-' + key"
        @focus="() => sendMessageToParent('activeBlock', indexBlock)"
        :uploadImageRoute="{
          name: webpageData.images_upload_route.name,
          parameters: { modelHasWebBlocks: blockData.id }
        }"
      />
    </div>
  </div>
</template>
