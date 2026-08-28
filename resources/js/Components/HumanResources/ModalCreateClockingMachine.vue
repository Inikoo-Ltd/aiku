<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faQrcode, faHashtag, faBarcodeScan, faCamera } from '@fal'
import { faAsterisk } from '@fas'

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'

library.add(faQrcode, faHashtag, faBarcodeScan, faCamera, faAsterisk)

const props = defineProps<{
    route: {
        name: string
        parameters: Record<string, string | number>
    }
    workplaces: {
        value: number
        label: string
    }[]
}>()

const CLOCKING_MACHINE_TYPE_QR_CODE = 'qr-code'
const CLOCKING_MACHINE_TYPE_PIN = 'pin'
const CLOCKING_MACHINE_TYPE_BARCODE_SCANNER = 'barcode-scanner'
const CLOCKING_MACHINE_TYPE_CAMERA_QR = 'camera-qr'

const machineTypes = [
    { value: CLOCKING_MACHINE_TYPE_QR_CODE, label: trans('QR Code'), icon: 'fal fa-qrcode' },
    { value: CLOCKING_MACHINE_TYPE_PIN, label: trans('PIN'), icon: 'fal fa-hashtag' },
    { value: CLOCKING_MACHINE_TYPE_BARCODE_SCANNER, label: trans('Barcode Scanner'), icon: 'fal fa-barcode-scan' },
    { value: CLOCKING_MACHINE_TYPE_CAMERA_QR, label: trans('Camera QR Scanner'), icon: 'fal fa-camera' },
]

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const machineName = ref('')
const machineType = ref(CLOCKING_MACHINE_TYPE_QR_CODE)
const workplaceId = ref<number | null>(null)

const hasWorkplaceChoice = computed(() => props.workplaces.length > 1)
const defaultWorkplaceId = computed(() => (props.workplaces.length === 1 ? props.workplaces[0].value : null))
const isFormInvalid = computed(() => !machineName.value.trim() || !machineType.value || !workplaceId.value)

const resetForm = () => {
    machineName.value = ''
    machineType.value = CLOCKING_MACHINE_TYPE_QR_CODE
    workplaceId.value = defaultWorkplaceId.value
}

watch(defaultWorkplaceId, () => resetForm(), { immediate: true })

const openModal = () => {
    resetForm()
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    resetForm()
}

const submit = async () => {
    isSubmitting.value = true

    try {
        await axios.post(route(props.route.name, props.route.parameters), {
            name: machineName.value.trim(),
            type: machineType.value,
            workplace_id: workplaceId.value,
        })

        notify({
            title: trans('Success'),
            text: trans('Clocking machine successfully created'),
            type: 'success',
        })

        closeModal()
        router.reload()
    } catch (error: any) {
        const errors = error.response?.data?.errors || {}
        notify({
            title: trans('Something went wrong'),
            text: Object.values(errors).flat().join('. ') || trans('Failed to submit the data, please try again'),
            type: 'error',
        })
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <div>
        <Button
            type="create"
            :label="trans('Clocking machine')"
            @click="openModal"
        />

        <Modal :isOpen="isModalOpen" width="w-full max-w-lg" @onClose="closeModal">
            <div class="p-1 space-y-4">
                <h2 class="text-2xl font-bold text-center">
                    {{ trans('New clocking machine') }}
                </h2>

                <div class="space-y-2">
                    <label for="clocking_machine_name" class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Name') }}:
                    </label>

                    <PureInput
                        id="clocking_machine_name"
                        v-model="machineName"
                        :placeholder="trans('Enter clocking machine name')"
                        @keydown.enter="!isFormInvalid && !isSubmitting && submit()"
                    />
                </div>

                <div class="space-y-2">
                    <div class="font-medium">{{ trans('Type') }}:</div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in machineTypes"
                            :key="option.value"
                            type="button"
                            class="inline-flex items-center gap-x-2 rounded-lg border px-3 py-2 font-semibold"
                            :class="machineType === option.value
                                ? 'border-green-500 bg-green-50 text-green-700'
                                : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'"
                            @click="machineType = option.value"
                        >
                            <FontAwesomeIcon :icon="option.icon" />
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div v-if="hasWorkplaceChoice" class="space-y-2">
                    <label class="font-medium flex items-center gap-x-1">
                        <FontAwesomeIcon icon="fas fa-asterisk" class="font-light text-xs text-red-400 align-middle" />
                        {{ trans('Workplace') }}:
                    </label>

                    <PureMultiselect
                        v-model="workplaceId"
                        :options="workplaces"
                        required
                        :placeholder="trans('Select workplace')"
                    />
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button type="cancel" @click="closeModal" />

                    <Button
                        full
                        icon="fad fa-save"
                        :label="isSubmitting ? trans('Loading') : trans('Save')"
                        :disabled="isFormInvalid || isSubmitting"
                        :loading="isSubmitting"
                        @click="submit"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
