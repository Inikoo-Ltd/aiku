<script setup lang="ts">
import { Dialog, DialogPanel, TransitionRoot, TransitionChild } from '@headlessui/vue'
import { trans } from 'laravel-vue-i18n'
import EditLocations from './EditLocations.vue'

const props = defineProps({
    modelValue: Boolean,
    locations: Array,
    routes: Object
})

const emits = defineEmits(['update:modelValue'])

const close = () => emits('update:modelValue', false)
</script>

<template>
<TransitionRoot as="template" :show="modelValue">
    <Dialog class="relative z-30" @close="close">

        <!-- overlay -->
        <TransitionChild
            enter="ease-out duration-200"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="ease-in duration-150"
            leave-from="opacity-100"
            leave-to="opacity-0"
        >
            <div class="fixed inset-0 bg-black/40" />
        </TransitionChild>

        <!-- container -->
        <div class="fixed inset-0 flex items-center justify-center px-2 sm:px-4 max-w-[95w]">
            <TransitionChild
                enter="ease-out duration-200"
                enter-from="opacity-0 scale-95"
                enter-to="opacity-100 scale-100"
                leave="ease-in duration-150"
                leave-from="opacity-100 scale-100"
                leave-to="opacity-0 scale-95"
            >
                <DialogPanel class="
                    bg-white rounded-xl py-4 px-6
                    w-[95vw]
                    sm:w-[90vw]
                    md:w-[80vw]
                    lg:w-[76vw]
                    xl:w-[50vw]
                    max-h-[80vh] overflow-auto
                ">
                    <!-- HEADER -->
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold">
                            {{ trans('Edit Locations') }}
                        </h2>
                        <button @click="close" class="text-gray-500 hover:text-gray-800">✕</button>
                    </div>

                    <!-- CONTENT -->
                    <EditLocations
                        :locations="locations"
                        :routes="routes"
                        @close="close"
                    />

                </DialogPanel>
            </TransitionChild>
        </div>

    </Dialog>
</TransitionRoot>
</template>