<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faQrcode } from '@fal'

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'

library.add(faQrcode)

const props = defineProps<{
    route: {
        name: string
        parameters: Record<string, string | number>
    }
    reloadOnly?: string
}>()

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const label = ref('')

const openModal = () => {
    label.value = ''
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    label.value = ''
}

const submit = async () => {
    isSubmitting.value = true

    try {
        await axios.post(route(props.route.name, props.route.parameters), {
            label: label.value.trim() || null,
        })

        notify({
            title: trans('Success'),
            text: trans('QR code successfully generated'),
            type: 'success',
        })

        closeModal()
        router.reload(props.reloadOnly ? { only: [props.reloadOnly] } : {})
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
            icon="fal fa-qrcode"
            :label="trans('QR code')"
            @click="openModal"
        />

        <Modal :isOpen="isModalOpen" width="w-full max-w-lg" @onClose="closeModal">
            <div class="p-1 space-y-4">
                <h2 class="text-2xl font-bold text-center">
                    {{ trans('Generate QR code') }}
                </h2>

                <div class="space-y-2">
                    <label for="clocking_machine_qr_code_label" class="font-medium">
                        {{ trans('Label') }}:
                    </label>

                    <PureInput
                        id="clocking_machine_qr_code_label"
                        v-model="label"
                        :placeholder="trans('Leave empty to generate a label automatically')"
                        @keydown.enter="!isSubmitting && submit()"
                    />
                </div>

                <div class="mt-8 flex justify-end gap-x-4">
                    <Button type="cancel" @click="closeModal" />

                    <Button
                        full
                        icon="fal fa-qrcode"
                        :label="isSubmitting ? trans('Loading') : trans('Generate')"
                        :disabled="isSubmitting"
                        :loading="isSubmitting"
                        @click="submit"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
