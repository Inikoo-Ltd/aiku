<script setup lang="ts">
import { ColorPicker } from 'vue-color-kit'
import 'vue-color-kit/dist/vue-color-kit.css'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { ref, watch } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPaintBrushAlt, faText } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faPaintBrushAlt, faText)

const props = withDefaults(defineProps<{
  modelValue?: string
  fieldData?: {
    placeholder?: string
    readonly?: boolean
    copyButton?: boolean
    icon?: string
  }
  colorSuggestions?: boolean
  stylePanel?: any
  mode?: string
}>(), {
  colorSuggestions: () => true,
  mode: 'color',
  fieldData: {
    icon: 'far fa-paint-brush'
  }
})

const emit = defineEmits(['update:modelValue'])

const color = ref(props.modelValue ?? 'rgba(55,65,81,1)')

watch(() => props.modelValue, (v) => {
  if (v !== color.value) color.value = v as string
})

watch(color, (v) => {
  emit('update:modelValue', v)
})

const changeColor = (value:any) => {
  const { r, g, b, a } = value.rgba
  const newColor = `rgba(${r}, ${g}, ${b}, ${a})`
  color.value = newColor
}

const changeScheme = (value:string) => {
  color.value = value
}
</script>

<template>
<div class="flex gap-3" v-if="mode==='color'">
  <Popover v-slot="{ open }">
    <div class="relative">
      <PopoverButton>
        <div
          class="border border-slate-300 rounded-full w-10 h-10 flex justify-center items-center"
          :style="`background-color:${color}`">
          <FontAwesomeIcon
            :icon="fieldData.icon?.length ? fieldData.icon : 'far fa-paint-brush-alt'"
            class="text-gray-300 text-lg" />
        </div>
      </PopoverButton>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0">

        <PopoverPanel
          v-show="open"
          class="absolute bottom-full left-1/2 z-10 mb-3 -translate-x-1/2 transform px-4 sm:px-0"
          :style="stylePanel">

          <ColorPicker
            theme="light"
            v-model="color"
            @changeColor="changeColor"
            style="width:225px"/>
        </PopoverPanel>
      </Transition>
    </div>
  </Popover>

  <div class="flex gap-x-1 items-center" v-if="colorSuggestions">
    <div class="bg-gray-700 border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:55,g:65,b:81,a:1 } })"/>
    <div class="bg-white border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:255,g:255,b:255,a:1 } })"/>
    <div class="bg-yellow-300 border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:253,g:224,b:71,a:1 } })"/>
    <div class="bg-emerald-500 border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:16,g:185,b:129,a:1 } })"/>
    <div class="bg-sky-500 border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:14,g:165,b:233,a:1 } })"/>
    <div class="bg-indigo-500 border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:99,g:102,b:241,a:1 } })"/>
    <div class="bg-rose-500 border rounded w-6 h-6 cursor-pointer"
      @click="() => changeColor({ rgba:{ r:244,g:63,b:94,a:1 } })"/>
  </div>
</div>

<div class="flex gap-3" v-if="mode==='scheme'">
  <Popover v-slot="{ open }">
    <div class="relative">
      <PopoverButton>
        <slot name="button">
          <div
            class="border border-slate-300 rounded-full w-10 h-10 flex justify-center items-center"
            :class="`bg-${color}-500`">
            <FontAwesomeIcon icon="far fa-text" class="text-gray-300 text-lg"/>
          </div>
        </slot>
      </PopoverButton>

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0">

        <PopoverPanel
          v-show="open"
          class="absolute bottom-full left-1/2 z-10 mb-3 -translate-x-1/2 transform px-4 sm:px-0"
          :style="stylePanel">

          <div class="flex gap-x-1 flex-wrap" style="width:225px;">
            <div class="bg-gray-500 border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('gray')" />
            <div class="bg-white border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('white')" />
            <div class="bg-yellow-500 border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('yellow')" />
            <div class="bg-green-500 border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('green')" />
            <div class="bg-blue-500 border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('blue')" />
            <div class="bg-fuchsia-500 border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('fuchsia')" />
            <div class="bg-red-500 border rounded w-6 h-6 cursor-pointer" @click="() => changeScheme('red')" />
          </div>
        </PopoverPanel>
      </Transition>
    </div>
  </Popover>
</div>
</template>

<style scoped>
.hu-color-picker{
  position:absolute;
  left:0;
  bottom:0;
}
</style>
