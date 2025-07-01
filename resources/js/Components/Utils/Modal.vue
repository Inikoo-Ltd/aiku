<script setup  lang="ts">
import { TransitionRoot, TransitionChild, Dialog, DialogPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTimes)

const props = withDefaults(defineProps<{
    width?: string
    isOpen: boolean
    closeButton?: boolean
    dialogStyle?: {}
    zIndex?: number
    isClosableInBackground?: boolean
}>(), {
    width: 'w-4/5',
    isClosableInBackground: true,
})


const emits = defineEmits()

const closeModal = () => {
    emits('onClose')
}

</script>


<template>
    <TransitionRoot appear :show="props.isOpen" as="template">
        <Dialog as="div" @close="isClosableInBackground ? closeModal() : false" class="relative" :style="{ zIndex: props.zIndex || 22}">
            <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
                leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/40" />
            </TransitionChild>

            <div class="fixed w-screen h-screen top-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center">
                    <TransitionChild as="template" enter="duration-300 ease-out" enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100" leave="duration-200 ease-in" leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95">
                        <DialogPanel
                            :class="`${props.width} transform overflow-visible rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all`"
                            :style="dialogStyle"
                        >
                            <!-- Button: Close -->
                            <div v-if="closeButton" @click="emits('onClose')" class="group px-2 absolute right-5 top-4 cursor-pointer">
                                <FontAwesomeIcon icon='fal fa-times' class='text-gray-400 group-hover:text-gray-600'
                                    aria-hidden='true' />
                            </div>
                            <slot />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

