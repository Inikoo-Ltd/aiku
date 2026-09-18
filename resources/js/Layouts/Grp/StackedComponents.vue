<script setup lang='ts'>
import { inject, onBeforeUnmount, onMounted } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTimes)

const layout = inject('layout', layoutStructure)

let previousRootOverflow = ''
let previousBodyPaddingRight = ''

const lockPageScroll = () => {
    const root = document.documentElement
    const scrollbarWidth = window.innerWidth - root.clientWidth

    previousRootOverflow = root.style.overflow
    previousBodyPaddingRight = document.body.style.paddingRight

    root.style.overflow = 'hidden'
    if (scrollbarWidth > 0) {
        document.body.style.paddingRight = `${scrollbarWidth}px`
    }
}

const unlockPageScroll = () => {
    document.documentElement.style.overflow = previousRootOverflow
    document.body.style.paddingRight = previousBodyPaddingRight
}

onMounted(lockPageScroll)
onBeforeUnmount(unlockPageScroll)

const getTranslateX = (listLength: number, idxComponent: number) => {
    if (listLength === 1) return 0

    const step = -50 / (listLength - 1)
    return (step * (listLength - (idxComponent + 1))) + (-8 * (listLength - (idxComponent + 1)))
}
</script>

<template>
    <div class="p-6 fixed top-0 left-0 h-screen w-screen flex justify-end isolate z-[100]">
        <template v-if="layout.stackedComponents.length">
            <TransitionGroup name="stacked-component">
                <div v-for="(component, idxComponent) in layout.stackedComponents"
                    :key="'stackedComponent' + idxComponent"
                    class="absolute top-0 left-0 h-screen w-screen flex justify-end isolate z-[100]">
                    <div @click="layout.stackedComponents.pop()" class="fixed inset-0 z-10 cursor-pointer" />

                    <!-- Panel -->
                    <div class="py-6 z-20 absolute h-screen w-10/12 transition-all overflow-y-auto overscroll-contain" :style="{
                        backgroundColor: '#fff',
                        transform: `translateX(${getTranslateX(layout.stackedComponents.length, idxComponent)}px)`
                    }">
                        <!-- Button: close -->
                        <div @click="layout.stackedComponents.pop()"
                            class="absolute right-5 top-5 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white/80 text-gray-500 shadow-sm hover:bg-white hover:text-gray-700 cursor-pointer">
                            <FontAwesomeIcon icon="fal fa-times" class="lg" fixed-width aria-hidden="true" />
                        </div>

                        <!-- Section: main component -->
                        <component :is="component.component" :data="component.data" />
                    </div>

                    <!-- Dimmed background for stacked layers -->
                    <Transition>
                        <div v-if="(idxComponent + 1) < layout.stackedComponents.length"
                            @click="layout.stackedComponents.pop()"
                            class="fixed inset-0 bg-black/40 z-30 cursor-pointer" />
                    </Transition>
                </div>
            </TransitionGroup>
        </template>
    </div>
</template>
