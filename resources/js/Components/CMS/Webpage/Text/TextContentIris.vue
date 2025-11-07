<script setup lang="ts">
import { getStyles } from '@/Composables/styles'
import { inject, computed } from 'vue';
import { get, isPlainObject, cloneDeep, set } from 'lodash-es'

const props = defineProps<{
    fieldValue: {
        value: string
        container: {
            properties: {}
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()
const layout: any = inject("layout", {})

const valueForField = computed({
  get() {
    const rawVal = get(props.fieldValue, ['value'])
    if (!isPlainObject(rawVal)) return rawVal

    if (!rawVal?.use_responsive) {
      return rawVal?.desktop ?? ''
    }

    const view = props.screenType!
    return rawVal?.[view] ?? rawVal?.desktop ?? ''
  },
  set(newVal) {
    const rawVal = cloneDeep(get(props.fieldValue, ['value']))

    if (!isPlainObject(rawVal)) {
      set(props.fieldValue, ['value'], newVal)
      return
    }
    if (!rawVal?.use_responsive) {
      set(props.fieldValue, ['value', 'desktop'], newVal)
    } else {
      set(props.fieldValue, ['value', props.screenType], newVal)
    }

  }
})

</script>

<template>
    <div id="text">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(fieldValue.container?.properties, screenType)
        }">
            <div class="editor-class" v-html="valueForField"></div>
        </div>

    </div>
</template>
