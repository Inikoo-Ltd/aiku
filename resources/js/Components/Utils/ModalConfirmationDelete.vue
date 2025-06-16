<script setup lang='ts'>
import { ref } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { Link, router } from '@inertiajs/vue3'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faExclamationTriangle } from '@fal'
import { faAsterisk } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Icon } from '@/types/Utils/Icon'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '../Pure/PureInput.vue'

library.add(faTimes, faExclamationTriangle, faAsterisk)

const props = defineProps<{
    description?: string
    icon?: Icon
    yesLabel?: string
    noLabel?: string
    noIcon?: string
    routeDelete?: routeType
    isFullLoading?: boolean
    isWithMessage?: boolean
    keyMessage?: string
    whyLabel?: string
    title?: string
    message?: {
        placeholder?: string
    }
}>()

const emits = defineEmits<{
    (e: 'onNo'): void
    (e: 'onYes'): void
}>()

const isOpenModal = ref(false)
const isLoadingdelete = ref(false)
const onClickDelete = () => {
    if (!props.routeDelete?.name) return

    const selectedMethod = props.routeDelete?.method || 'delete'
    const body = selectedMethod !== 'delete' ? {
        [props.keyMessage || 'delete_comment']: messageDelete.value
    } : undefined

    if (selectedMethod === 'delete') {
        router.delete(
            route(
                props.routeDelete.name, props.routeDelete.parameters),
                {
                    onStart: () => {
                        isLoadingdelete.value = true
                    },
                    onSuccess: () => {
                        isOpenModal.value = false
                    },
                    onFinish: () => {
                        if (props.isFullLoading) {
    
                        } else {
                            isLoadingdelete.value = false
                        }
                    }
                }
        )
    } else {
        router[selectedMethod](
            route(
                props.routeDelete.name, props.routeDelete.parameters),
                body,
                {
                    onStart: () => {
                        isLoadingdelete.value = true
                    },
                    onSuccess: () => {
                        isOpenModal.value = false
                    },
                    onFinish: () => {
                        if (props.isFullLoading) {
    
                        } else {
                            isLoadingdelete.value = false
                        }
                    }
                }
        )
    }
}

const messageDelete = ref('')
</script>

<template>
    <div>

        <slot name="default" :isOpenModal :changeModel="() => isOpenModal = !isOpenModal" :isLoadingdelete>

        </slot>

        <TransitionRoot as="template" :show="isOpenModal">
            <Dialog class="relative z-20" @close="isOpenModal = false">
                <TransitionChild as="template" enter="ease-out duration-150" enter-from="opacity-0" enter-to="opacity-100"
                    leave="ease-in duration-100" leave-from="opacity-100" leave-to="opacity-0">
                    <div class="z-10 fixed inset-0 bg-gray-500/75 transition-opacity" />
                </TransitionChild>
                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <TransitionChild as="template" enter="ease-out duration-150"
                            enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-100"
                            leave-from="opacity-100 translate-y-0 sm:scale-100"
                            leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                            <DialogPanel
                                class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                                <div class="absolute top-0 right-0 hidden pt-4 pr-4 sm:block">
                                    <button type="button"
                                        class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                                        @click="isOpenModal = false">
                                        <span class="sr-only">Close</span>
                                        <FontAwesomeIcon :icon='icon || "fal fa-times"' class='' fixed-width aria-hidden='true' />
                                    </button>
                                </div>

                                <div class="sm:flex sm:items-start">
                                    <div
                                        class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10">
                                        <FontAwesomeIcon icon='fal fa-exclamation-triangle' class='text-red-600' fixed-width aria-hidden='true' />
                                    </div>
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                        <DialogTitle as="h3" class="text-base font-semibold">
                                            {{ title || trans("Are you sure want to delete?") }}
                                        </DialogTitle>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-500">
                                                {{ description || trans("The data will be permanently delete 😥 .This action cannot be undone.")}}
                                            </p>
                                        </div>
                                      
                                        <div v-if="props.isWithMessage" class="mt-4">
                                            <label for="" class="flex items-start text-sm text-gray-500 leading-none mb-1">
                                                {{ whyLabel || trans("Why you deleting this?") }}
                                                <FontAwesomeIcon icon='far fa-asterisk' class='text-red-500 h-2' size="xs" fixed-width aria-hidden='true' />
                                            </label>

                                            <PureInput
                                                v-model="messageDelete"
                                                :placeholder="props.message?.placeholder || trans('Enter the reason for deleting')"
                                                v-bind="props.message"
                                            />
                                        </div>
                                        <div class="mt-5 sm:flex sm:flex-row-reverse gap-x-2">
                                            <Button
                                                :loading="isLoadingdelete"
                                                @click="() => (onClickDelete(), emits('onYes'))"
                                                type="red"
                                                :label="props.noLabel ?? trans('Delete')"
                                                :disabled="isWithMessage ? !messageDelete : false"
                                                :icon="props.noIcon ?? 'far fa-trash-alt'"
                                            />
        
                                            <!-- <button type="button"
                                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto"
                                                @click="() => ">{{ trans("Yes, delete") }}</button> -->
                                            <Button
                                                type="tertiary"
                                                icccon="far fa-arrow-left"
                                                :label="trans('cancel')"
                                                full
                                                @click="() => (isOpenModal = false, emits('onNo'))"
                                            />
                                            <!-- <button type="button"
                                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold ring-1 shadow-xs ring-gray-300 ring-inset hover:bg-gray-50 sm:mt-0 sm:w-auto"
                                                >{{ trans("Cancel") }}</button> -->
        
                                        </div>
                                    </div>

                                </div>

                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>