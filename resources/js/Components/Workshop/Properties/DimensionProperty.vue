<script setup lang='ts'>
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Popover, PopoverButton, PopoverPanel, Switch } from '@headlessui/vue'
import { faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faArrowsAltV, faArrowsH } from "@fad"
import { faLink, faUnlink } from "@fal"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from "@fortawesome/fontawesome-svg-core"
import { get, set } from 'lodash-es'
import { InputNumber, Slider } from 'primevue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'

library.add(faBorderTop, faBorderLeft, faBorderBottom, faBorderRight, faBorderOuter, faLink, faUnlink, faArrowsAltV, faArrowsH)

const model = defineModel()

const emits = defineEmits<{
  (e: 'update:modelValue', value: any): void
}>()

const customWidthEnabled = ref(false)
</script>

<template>
  <div class="space-y-5 pt-1 pb-4">
    <!-- Section: Height -->
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm p-4">
      <div class="flex justify-between items-center mb-3">
        <span class="text-xs font-medium text-gray-600 uppercase tracking-wide">
          {{ trans('Height') }}
        </span>
        <Popover v-slot="{ open }" class="relative">
          <PopoverButton :class="open ? 'text-indigo-600 font-semibold' : 'text-gray-500'" class="text-xs underline">
            {{ model?.height?.unit || '%' }}
          </PopoverButton>
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
          >
            <PopoverPanel class="absolute right-0 z-10 mt-2 w-28 rounded-md border border-gray-200 bg-white shadow-lg">
              <div
                class="px-4 py-2 cursor-pointer text-sm"
                :class="model?.height.unit === 'px' ? 'bg-indigo-500 text-white' : 'hover:bg-gray-100'"
                @click="() => { model.height.unit = 'px'; emits('update:modelValue', {...model}) }"
              >px</div>
              <div
                class="px-4 py-2 cursor-pointer text-sm"
                :class="model?.height.unit === '%' ? 'bg-indigo-500 text-white' : 'hover:bg-gray-100'"
                @click="() => { model.height.unit = '%'; emits('update:modelValue', {...model}) }"
              >%</div>
            </PopoverPanel>
          </transition>
        </Popover>
      </div>

      <div class="grid grid-cols-12 items-center gap-3">
        <FontAwesomeIcon icon="fad fa-border-outer" class="text-gray-400 text-base col-span-1" />
        <Slider
          :modelValue="get(model, 'height.value', 0)"
          @update:modelValue="newVal => (set(model, 'height.value', newVal), emits('update:modelValue', {...model}))"
          class="col-span-7"
          :max="300"
        />
         <PureInputNumber
          :modelValue="get(model, 'height.value', 0)"
          @update:modelValue="newVal => (set(model, 'height.value', newVal), emits('update:modelValue', {...model}))"
          :suffix="model?.width?.unit || '%'"
          class="min-w-[80px] col-span-4"
        />
      </div>
    </div>

    <!-- Toggle: Customize Width -->
    <div class="flex items-center gap-3 px-1">
      <Switch
        v-model="customWidthEnabled"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
        :class="customWidthEnabled ? 'bg-indigo-500' : 'bg-gray-300'"
      >
        <span
          aria-hidden="true"
          class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
          :class="customWidthEnabled ? 'translate-x-5' : 'translate-x-0'"
        />
      </Switch>
      <span class="text-sm text-gray-700">{{ trans('Customize Width') }}</span>
    </div>

    <!-- Section: Width -->
    <div v-if="customWidthEnabled" class="rounded-lg border border-gray-200 bg-white shadow-sm p-4">
      <div class="flex justify-between items-center mb-3">
        <span class="text-xs font-medium text-gray-600 uppercase tracking-wide">
          {{ trans('Width') }}
        </span>
        <Popover v-slot="{ open }" class="relative">
          <PopoverButton :class="open ? 'text-indigo-600 font-semibold' : 'text-gray-500'" class="text-xs underline">
            {{ model?.width?.unit || '%' }}
          </PopoverButton>
          <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-1 opacity-0"
          >
            <PopoverPanel class="absolute right-0 z-10 mt-2 w-28 rounded-md border border-gray-200 bg-white shadow-lg">
              <div
                class="px-4 py-2 cursor-pointer text-sm"
                :class="model?.width.unit === 'px' ? 'bg-indigo-500 text-white' : 'hover:bg-gray-100'"
                @click="() => { model.width.unit = 'px'; emits('update:modelValue', {...model}) }"
              >px</div>
              <div
                class="px-4 py-2 cursor-pointer text-sm"
                :class="model?.width.unit === '%' ? 'bg-indigo-500 text-white' : 'hover:bg-gray-100'"
                @click="() => { model.width.unit = '%'; emits('update:modelValue', {...model}) }"
              >%</div>
            </PopoverPanel>
          </transition>
        </Popover>
      </div>

      <div class="grid grid-cols-12 items-center gap-3">
        <FontAwesomeIcon icon="fad fa-border-outer" class="text-gray-400 text-base col-span-1" />
        <Slider
          :modelValue="get(model, 'width.value', 0)"
          @update:modelValue="newVal => (set(model, 'width.value', newVal), emits('update:modelValue', {...model}))"
          class="col-span-7"
          :max="300"
        />
        <PureInputNumber
          :modelValue="get(model, 'width.value', 0)"
          @update:modelValue="newVal => (set(model, 'width.value', newVal), emits('update:modelValue', {...model}))"
          :suffix="model?.width?.unit || '%'"
          class="min-w-[80px] col-span-4"
        />
      </div>
    </div>
  </div>
</template>
