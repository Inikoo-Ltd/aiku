<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faQrcode } from '@fal'
import { faAsterisk } from '@fas'

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'

library.add(faQrcode, faAsterisk)

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

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const machineName = ref('')
const workplaceId = ref<number | null>(null)

const hasWorkplaceChoice = computed(() => props.workplaces.length > 1)
const defaultWorkplaceId = computed(() => (props.workplaces.length === 1 ? props.workplaces[0].value : null))
const isFormInvalid = computed(() => !machineName.value.trim() || !workplaceId.value)

const resetForm = () => {
    machineName.value = ''
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
            type: CLOCKING_MACHINE_TYPE_QR_CODE,
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

                    <div class="inline-flex items-center gap-x-2 rounded-lg border border-green-500 bg-green-50 px-3 py-2 font-semibold text-green-700">
                        <FontAwesomeIcon icon="fal fa-qrcode" />
                        {{ trans('QR Code') }}
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
