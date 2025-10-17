<script setup lang='ts'>
import codemirrorPkg from 'vue-codemirror'
import { json } from "@codemirror/lang-json"
import { computed } from 'vue'

const props = defineProps<{
  form?: Record<string, any>
  fieldName: string
  options?: string[] | Record<string, any>
}>()

const { Codemirror } = codemirrorPkg
const extensions = [json()]

// Safe computed binding: always a string
const codeValue = computed({
  get() {
    const value = props.form?.[props.fieldName]
    return typeof value === 'string' ? value : value ? JSON.stringify(value, null, 2) : ''
  },
  set(newValue) {
    if (props.form) {
      props.form[props.fieldName] = newValue || null
    }
  }
})
</script>

<template>
  <div class="max-w-2xl rounded-md">
    <div class="mt-3">
      <label class="text-gray-600 font-semibold cursor-pointer">
        SEO Structured Data (JSON-LD)
      </label>
      <Codemirror
        v-model="codeValue"
        :style="{
          height: '500px',
          textOverflow: 'ellipsis',
          border: '1px solid #ddd'
        }"
        :autofocus="true"
        :indent-with-tab="true"
        :tab-size="2"
        :extensions="extensions"
      />
    </div>
  </div>
</template>
