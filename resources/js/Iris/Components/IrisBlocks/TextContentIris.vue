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
    indexBlock?:number | string
    code?: string
}>()
const layout: any = inject("layout", {})

const rawValue = computed(() => get(props.fieldValue, ['value']))
const isResponsive = computed(() => isPlainObject(rawValue.value) && !!rawValue.value?.use_responsive)

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
    <div :id="fieldValue?.id ? fieldValue?.id  : 'text'+indexBlock"  component="text">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType,true,false),
            ...getStyles(fieldValue.container?.properties, screenType, true,false)
        }">
            <template v-if="isResponsive">
                <div class="editor-class sm:!hidden" v-html="rawValue?.mobile ?? rawValue?.desktop ?? ''"></div>
                <div class="editor-class max-sm:!hidden lg:!hidden" v-html="rawValue?.tablet ?? rawValue?.desktop ?? ''"></div>
                <div class="editor-class max-lg:!hidden" v-html="rawValue?.desktop ?? ''"></div>
            </template>
            <div v-else class="editor-class" v-html="valueForField"></div>
        </div>

    </div>
</template>
