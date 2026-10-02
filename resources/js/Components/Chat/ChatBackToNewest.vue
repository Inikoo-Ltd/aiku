<script setup lang="ts">
import { computed, toRef } from "vue"
import { useScroll } from "@vueuse/core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    target: HTMLElement | null | undefined
}>()

const { arrivedState, y } = useScroll(toRef(props, "target"), { offset: { bottom: 80 } })

const isAwayFromNewest = computed(() => {
    const element = props.target

    return Boolean(element) && y.value >= 0 && !arrivedState.bottom && element!.scrollHeight > element!.clientHeight
})

const backToNewest = () => {
    props.target?.scrollTo({ top: props.target.scrollHeight, behavior: "smooth" })
}
</script>

<template>
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
