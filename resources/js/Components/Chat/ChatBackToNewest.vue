<script setup lang="ts">
import { ref, toRef } from "vue"
import { useIntersectionObserver } from "@vueuse/core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    target: HTMLElement | null | undefined
}>()

/**
 * The browser says when the end of the messages comes within 80 px of view (72 px plus the
 * thread's 8 px bottom padding), so scrolling runs no code of ours: a scroll listener that measured
 * the thread on every event was a production hot spot (INI-145).
 */
const newestMarker = ref<HTMLElement | null>(null)
const isAwayFromNewest = ref(false)

useIntersectionObserver(newestMarker, (entries) => {
    isAwayFromNewest.value = !entries[entries.length - 1].isIntersecting
}, {
    root: toRef(props, "target"),
    rootMargin: "0px 0px 72px 0px",
    threshold: 0,
})

const backToNewest = () => {
    props.target?.scrollTo({ top: props.target.scrollHeight, behavior: "smooth" })
}
</script>

<template>
    <div ref="newestMarker" class="!mt-0 h-0" aria-hidden="true" />
    <div class="pointer-events-none sticky bottom-2 z-10 !mt-0 flex h-0 justify-end pr-1">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 translate-y-1">
            <button v-show="isAwayFromNewest" type="button" v-tooltip="ctrans('Back to newest')" :aria-label="ctrans('Back to newest')"
                class="pointer-events-auto -mt-9 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-200 hover:text-gray-800"
                @click="backToNewest">
                <FontAwesomeIcon :icon="faChevronDown" class="text-sm" fixed-width aria-hidden="true" />
            </button>
        </Transition>
    </div>
</template>
