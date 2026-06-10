<script setup lang='ts'>
import Button from '@/Components/Elements/Buttons/Button.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { ctrans } from '@/Composables/useTrans'
import { notify } from '@kyvg/vue3-notification'
import { computed, inject, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useFormatTime } from '@/Composables/useFormatTime'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import PureInput from '@/Components/Pure/PureInput.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTag } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Modal from '@/Components/Utils/Modal.vue'
library.add(faTag)

const props = defineProps<{
    voucher: {
        id: number
        voucher_code: string
        voucher_amount: number
        state: string
        status: string
        start_at: string
        end_at: string
        name: string
        discount: number
    } | null
    order: {
        id: number
    }
}>()

const layout = inject('layout', retinaLayoutStructure)

// Section: Voucher
const isLoadingVoucher = ref(false)
const isModalVoucherNotFound = ref(false)
const voucherNotFoundMessage = ref('')
const currentVoucher = ref(props.voucher || {
    id: 0,
    voucher_code: '',
    voucher_amount: 0,
    status: '',
    end_at: '',
    name: '',
    discount: 0
})
const hasAttachedVoucher = computed(() => Boolean(currentVoucher.value?.voucher_code))
const isVoucherExpired = computed(() => currentVoucher.value?.status === 'expired')
const tempVoucherCode = ref('')

watch(
    () => props?.voucher?.voucher_code,
    (newVoucherCode) => {
        tempVoucherCode.value = newVoucherCode
        currentVoucher.value = props.voucher || {
            id: 0,
            voucher_code: '',
            voucher_amount: 0,
            status: '',
            start_at: '',
            end_at: '',
            name: '',
            discount: 0
        }
    },
    { immediate: true }
)

const onApplyVoucher = () => {
    if (!tempVoucherCode.value.trim()) {
        return
    }

    if (hasAttachedVoucher.value && currentVoucher.value.voucher_code !== tempVoucherCode.value) {
        notify({
            title: ctrans("Only one voucher can be attached"),
            text: ctrans("Remove current voucher first before adding a new one."),
            type: "warning"
        })
        return
    }

    router.post(
        route('retina.models.order.store_voucher', { order: props.order.id }),
        { voucher: tempVoucherCode.value },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingVoucher.value = true
            },
            onSuccess: () => {
                notify({
                    title: ctrans("Success"),
                    text: ctrans("Voucher added to your basket."),
                    type: "success"
                })
                layout?.reload_handle?.()
            },
            onError: (errors: Record<string, string>) => {
                if (errors?.voucher) {
                    voucherNotFoundMessage.value = errors.voucher
                    // isModalVoucherNotFound.value = true
                    // // return
                }
                console.log('errrorr', errors.voucher)
                notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Failed to add the voucher, try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingVoucher.value = false
            },
        }
    )
}

const onRemoveVoucher = () => {
    if (!hasAttachedVoucher.value) return

    router.post(
        route('retina.models.order.remove_voucher', { order: props.order.id }),
        { voucher: null },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingVoucher.value = true
            },
            onSuccess: () => {
                currentVoucher.value = {
                    id: 0,
                    voucher_code: '',
                    voucher_amount: 0,
                    state: '',
                    status: '',
                    start_at: '',
                    end_at: '',
                    name: '',
                    discount: 0
                }
                notify({
                    title: ctrans("Success"),
                    text: ctrans("Voucher removed from your basket."),
                    type: "success"
                })
                layout?.reload_handle?.()
            },
            onError: () => {
                notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Failed to remove voucher, try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingVoucher.value = false
            }
        }
    )
}

</script>

<template>
    <div>
        <!-- Voucher: active -->
        <div v-if="hasAttachedVoucher">
            <div class="flex flex-wrap items-stretch justify-end gap-x-3 gap-y-2 pr-2 md:pr-6">
                <div class="w-72 shrink-0">
                    <!-- <InputText type="text" v-model="voucherCode" size="small" /> -->
                    <PureInput
                        :modelValue="currentVoucher.voucher_code + (currentVoucher.discount ? ` - ${currentVoucher.discount}` : '')"
                        xisLoading="isLoadingVoucher"
                        @onEnter="() => onApplyVoucher()"
                        :disabled="isLoadingVoucher || hasAttachedVoucher"
                        class="!bg-green-100 font-bold !border !border-green-500"
                        :prefix="{
                            icon: 'fas fa-tag'
                        }"
                        :styleInput="{
                            paddingTop: '5px',
                            paddingBottom: '5px',
                            xborder: '1px solid rgb(34 197 94 / var(--tw-border-opacity, 1))'
                        }"
                        classInput="!bg-transparent !text-green-700 "
                    >
                        <template #prefix>
                            <div class="pl-3 -mr-2 whitespace-nowrap text-green-700">
                                <FontAwesomeIcon icon='fas fa-tag' class='' fixed-width aria-hidden='true' />
                            </div>
                        </template>
                        <template v-if="currentVoucher?.name" #suffix>
                            <div class="text-green-700 flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer">
                                <InformationIcon :information="currentVoucher?.name" />
                            </div>
                        </template>
                    </PureInput>

                    <div class="text-right text-xs italic opacity-70 mt-0.5 text-green-700 pr-1">
                        {{ ctrans('Voucher valid until :voucherUntil', { voucherUntil: useFormatTime(currentVoucher.end_at, { formatTime: 'hm'}) }) }}
                    </div>
                </div>

                <div class="h-8 flex">
                    <Button
                        class="shrink-0"
                        size="xs"
                        xlabel="ctrans('Remove voucher')"
                        icon="fal fa-trash-alt"
                        type="negative"
                        :loading="isLoadingVoucher"
                        @click="() => onRemoveVoucher()"
                        :disabled="isLoadingVoucher"
                    />
                </div>
            </div>

            
        </div>
        
        <!-- Voucher: not active -->
        <div v-else class="flex flex-wrap items-stretch justify-end gap-x-3 gap-y-2 pr-2 md:pr-6" v-if="layout.app.environment == 'local' && layout.retina.type == 'b2b'">
            <div class="w-72 shrink-0">
                <PureInput
                    v-model="tempVoucherCode"
                    @update:model-value="() => voucherNotFoundMessage = null"
                    xisLoading="isLoadingVoucher"
                    @onEnter="() => onApplyVoucher()"
                    :disabled="isLoadingVoucher || hasAttachedVoucher"
                    :placeholder="ctrans('Enter voucher code')"
                    xclass="!bg-green-100 font-bold !border !border-green-500"
                    :prefix="{
                        icon: 'fas fa-tag'
                    }"
                    :styleInput="{
                        paddingTop: '5px',
                        paddingBottom: '5px',
                        xborder: '1px solid rgb(34 197 94 / var(--tw-border-opacity, 1))'
                    }"
                    classInput="!bg-transparent xtext-green-700 "
                    :isError="!!(voucherNotFoundMessage?.length)"
                >
                    <template #prefix>
                        <div class="pl-3 -mr-2 whitespace-nowrap xtext-green-700 opacity-50">
                            <FontAwesomeIcon icon='fas fa-tag' class='' fixed-width aria-hidden='true' />
                        </div>
                    </template>
                    <template v-if="currentVoucher?.name" #suffix>
                        <div class="xtext-green-700 flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer">
                            <InformationIcon :information="currentVoucher?.name" />
                        </div>
                    </template>
                </PureInput>
                <div class="relative w-fit ml-auto">
                    <Transition name="slide-to-right">
                        <div v-if="voucherNotFoundMessage?.length" class="text-right text-xs italic opacity-90 mt-0.5 text-red-500 pr-1">
                            *{{ voucherNotFoundMessage }}
                        </div>
                    </Transition>
                </div>
            </div>

            <div class="flex h-8">
                <Button
                    class="shrink-0"
                    size="xs"
                    xlabel="ctrans('Add voucher')"
                    icon="fas fa-plus"
                    type="dashed"
                    :loading="isLoadingVoucher"
                    @click="() => onApplyVoucher()"
                    :disabled="!tempVoucherCode || isLoadingVoucher || hasAttachedVoucher"
                />
            </div>
        </div>

        <!-- <div v-if="layout.app.environment == 'local' && layout.retina.type == 'b2b'" class="mt-2 pr-2 md:pr-6">
            <div v-if="!hasAttachedVoucher" class="flex items-center justify-end">
                <div class="w-full md:w-[540px] border border-dashed border-gray-300 rounded-md px-3 py-2 text-right text-sm text-gray-500">
                    {{ trans('No voucher attached') }}
                </div>
            </div>
            <div v-else-if="isVoucherExpired" class="flex items-center justify-end">
                <div class="w-full md:w-[540px] border border-red-200 bg-red-50 rounded-md px-3 py-2 text-right text-sm text-red-700">
                    <span class="font-medium">{{ trans('Voucher expired') }}</span>
                    <span class="ml-1">({{ currentVoucher?.voucher_code }})</span>
                    <span class="ml-2 text-red-600">{{ trans('Until') }}: {{ voucherUntilLabel }}</span>
                </div>
            </div>
            <div v-else class="flex items-center justify-end">
                <div class="w-full md:w-[540px] border border-green-200 bg-green-50 rounded-md px-3 py-2 text-right text-sm text-green-700">
                    <span class="font-medium">{{ trans('Voucher active') }}</span>
                    <span class="ml-1">({{ currentVoucher?.voucher_code }})</span>
                    <span class="ml-2">{{ currentVoucher?.name }}</span>
                    <span class="ml-2">{{ currentVoucher?.discount }}</span>
                    <span class="ml-2 text-green-600">{{ trans('Until') }}: {{ voucherUntilLabel }}</span>
                </div>
            </div>
        </div> -->
    </div>

    

    <Modal :isOpen="isModalVoucherNotFound" @onClose="isModalVoucherNotFound = false" width="w-full max-w-md">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-xl text-red-500" fixed-width aria-hidden="true" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ ctrans("Voucher not found") }}</h3>
            <p class="mt-2 text-sm text-gray-500">
                {{ voucherNotFoundMessage || ctrans("The voucher code you entered was not found or is no longer available.") }}
            </p>
            <div class="mt-6 flex justify-center">
                <Button :label="ctrans('OK')" @click="isModalVoucherNotFound = false" />
            </div>
        </div>
    </Modal>
</template>