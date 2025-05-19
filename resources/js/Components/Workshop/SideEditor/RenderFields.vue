<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import ScreenView from '@/Components/ScreenView.vue'

import { get, isPlainObject } from 'lodash-es'
import { routeType } from '@/types/route'
import { getComponent } from '@/Composables/SideEditorHelper'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faInfoCircle)

const props = defineProps<{
  blueprint: {
    name: String,
    type: String,
    key: string | string[],
    useIn: Array<string>,
    label?: string,
    information?: string,
    props_data?: any
  },
  uploadImageRoute?: routeType,
}>()

const modelValue = defineModel()
const emits = defineEmits<{
  (e: 'update:modelValue', key: string | string[], value: any): void
}>()

const currentView = ref('desktop')

const valueForField = computed(() => {
  const rawVal = get(modelValue.value, props.blueprint.key)
  const useIn = props.blueprint.useIn

  if (!Array.isArray(useIn) || useIn.length === 0) {
    return rawVal
  }

  if (!isPlainObject(rawVal)) {
    return rawVal
  }

  return rawVal?.[currentView.value!]
})

const onPropertyUpdate = (newVal: any, path: any) => {
  const rawKey = Array.isArray(path) ? path : props.blueprint.key
  const prevVal = get(modelValue.value, rawKey)
  const useIn = props.blueprint.useIn

  if (!Array.isArray(useIn) || useIn.length === 0) {
    emits('update:modelValue', rawKey, newVal)
    return
  }

  const current = isPlainObject(prevVal) ? { ...prevVal } : {}
  const updatedValue = {
    ...current,
    [currentView.value]: newVal
  }

  emits('update:modelValue', rawKey, updatedValue)
}



</script>

<template>
  <div v-if="blueprint.label" class="w-full my-2 py-1 border-b border-gray-300 text-sm select-none">
    <div class="flex items-center justify-between">
      <div class="flex items-center font-semibold text-start">
        {{ trans(blueprint.label) }}
        <VTooltip v-if="blueprint.information" class="inline w-fit" placement="right">
          <FontAwesomeIcon
            icon="fal fa-info-circle"
            class="ml-1 text-gray-500 cursor-pointer"
            fixed-width
            aria-hidden="true"
          />
          <template #popper>
            <div class="min-w-20 w-fit max-w-64 text-xs">
              {{ blueprint.information }}
            </div>
          </template>
        </VTooltip>
      </div>
      <ScreenView
        :show-list="blueprint.useIn || []"
        :currentView="currentView"
        @screen-view="e => currentView = e"
      />
    </div>
  </div>

  <!-- <pre>{{get(modelValue, props.blueprint.key)}}</pre> -->
  <component
    :is="getComponent(blueprint.type)"
    :uploadRoutes="uploadImageRoute"
    v-bind="blueprint?.props_data"
    :modelValue="valueForField"
    @update:modelValue="onPropertyUpdate"
  />
</template>

<style lang="scss" scoped>
.editor-content {
  background-color: white;
  border: solid;
}

.p-inputtext {
  width: 100%;
}

.p-accordionpanel.p-accordionpanel-active > .p-accordionheader {
  background-color: #433cc3 !important;
  border-radius: 0 !important;
  color: #fdfdfd !important;
}

.p-accordioncontent-content {
  padding: 10px !important;
  background-color: #f9f9f9 !important;
}
</style>
