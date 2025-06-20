<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';


const props = withDefaults(defineProps<{
  width?: string | number | null | any
  position?: string
  disabled?: boolean
}>(), {
  width: 'w-fit',
  position: 'right-0'
})

</script>

<template>
  <Popover :popover-placement="'bottom-start'" class="focus-visible:ring-0" disabled>
    <PopoverButton tabindex="-1" v-slot="{ open, close }"  class="focus-visible:ring-0 w-full h-full">
      <slot name="button" :open="open" :close="close"></slot>
    </PopoverButton> 

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <PopoverPanel v-slot="{ open, close }" ref="panelPopover"
        :class="`absolute z-50 mt-3 transform py-3 px-4 bg-white border border-gray-200 rounded-md shadow-md  ${position} ${width}`" >
        <!-- Pass closePopover method to content slot -->
        <slot name="content" :open="open" :close="close"></slot>
      </PopoverPanel>
    </transition>
  </Popover>
</template>

<style lang="scss">
[data-headlessui-state] {
  @apply focus-visible:ring-transparent;
}
</style>
