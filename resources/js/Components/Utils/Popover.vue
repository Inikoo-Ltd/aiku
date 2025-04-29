<script setup>
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { ref } from 'vue';

const open = ref(false);

const props = defineProps({
  width: {
    type: String,
    default: 'w-4/5',
  },
  position: {
    type: String,
    default: 'right-0',
  },
});



</script>

<template>
  <Popover :popover-placement="'bottom-start'">
    <PopoverButton tabindex="-1" v-slot="{ open }">
      <slot name="button" :open></slot>
    </PopoverButton>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <PopoverPanel v-slot="{ close }" ref="panelPopover"
        :class="`border border-gray-800/10 absolute z-50 mt-3 transform py-3 px-4 bg-white rounded-md shadow-md w-fit ${position}`" >
        <!-- Pass closePopover method to content slot -->
        <slot name="content" :close :open></slot>
      </PopoverPanel>
    </transition>
  </Popover>
</template>

<style lang="scss">
[data-headlessui-state] {
  @apply focus-visible:ring-transparent;
}
</style>
