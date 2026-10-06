<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { onBeforeUnmount, ref, watch } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronUp, faChevronDown } from '@fal'
import { ctrans } from '@/Composables/useTrans'

library.add(faChevronUp, faChevronDown)

const props = withDefaults(defineProps<{
  width?: string | number | null | any
  position?: string
  disabled?: boolean
  fitViewport?: boolean
  arrow?: 'right' | null
}>(), {
  width: 'w-fit',
  position: 'right-0',
  fitViewport: false,
  arrow: null,
})

const viewportGapInPixels = 16
const minimumHeightInPixels = 160

const _scroller = ref<HTMLElement | null>(null)
const canScrollUp = ref(false)
const canScrollDown = ref(false)
let resizeObserver: ResizeObserver | null = null

const updateArrows = () => {
  const element = _scroller.value
  if (!element) return
  canScrollUp.value = element.scrollTop > 1
  canScrollDown.value = element.scrollTop + element.clientHeight < element.scrollHeight - 1
}

const scrollBy = (direction: 1 | -1) => {
  _scroller.value?.scrollBy({ top: direction * _scroller.value.clientHeight * 0.7, behavior: 'smooth' })
}

watch(_scroller, (element) => {
  resizeObserver?.disconnect()
  canScrollUp.value = false
  canScrollDown.value = false
  if (!element) return

  requestAnimationFrame(() => {
    const top = element.getBoundingClientRect().top
    element.style.maxHeight = `${Math.max(minimumHeightInPixels, window.innerHeight - top - viewportGapInPixels)}px`
    updateArrows()
  })

  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver ??= new ResizeObserver(updateArrows)
    resizeObserver.observe(element)
    Array.from(element.children).forEach((child) => resizeObserver?.observe(child))
  }
}, { flush: 'post' })

onBeforeUnmount(() => resizeObserver?.disconnect())
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
        :class="[`absolute z-50 mt-3 transform bg-white border border-gray-200 rounded-md shadow-md  ${position} ${width}`, fitViewport ? '' : 'py-3 px-4', arrow === 'right' ? 'rounded-tr-none' : '']" >
        <svg v-if="arrow === 'right'" aria-hidden="true" width="11" height="11" viewBox="0 0 11 11"
          class="pointer-events-none absolute -right-[11px] -top-px z-10 overflow-visible">
          <path d="M0 0 H11 L1 10 H0 Z" fill="#ffffff" />
          <path d="M0 0.5 H10 L0.5 10" fill="none" stroke="#e5e7eb" stroke-width="1" />
        </svg>
        <template v-if="fitViewport">
          <div ref="_scroller" class="overflow-y-auto rounded-md px-4 py-3" @scroll.passive="updateArrows">
            <slot name="content" :open="open" :close="close"></slot>
          </div>
          <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0">
            <button v-if="canScrollUp" type="button" :aria-label="ctrans('Scroll up')"
              class="absolute inset-x-0 top-0 flex h-7 items-start justify-center rounded-t-md bg-gradient-to-b from-white via-white/90 to-transparent pt-1 text-xs text-gray-500 hover:text-gray-800"
              @click="scrollBy(-1)">
              <FontAwesomeIcon icon="fal fa-chevron-up" fixed-width aria-hidden="true" />
            </button>
          </Transition>
          <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0">
            <button v-if="canScrollDown" type="button" :aria-label="ctrans('Scroll down')"
              class="absolute inset-x-0 bottom-0 flex h-7 items-end justify-center rounded-b-md bg-gradient-to-t from-white via-white/90 to-transparent pb-1 text-xs text-gray-500 hover:text-gray-800"
              @click="scrollBy(1)">
              <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" />
            </button>
          </Transition>
        </template>
        <!-- Pass closePopover method to content slot -->
        <slot v-else name="content" :open="open" :close="close"></slot>
      </PopoverPanel>
    </transition>
  </Popover>
</template>

<style lang="scss">
[data-headlessui-state] {
  @apply focus-visible:ring-transparent;
}
</style>
