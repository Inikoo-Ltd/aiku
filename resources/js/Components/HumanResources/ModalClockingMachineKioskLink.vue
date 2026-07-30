<script setup lang="ts">
import { ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTabletAlt, faLink, faCopy, faSyncAlt } from '@fal'

import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import type { ClockingMachine } from '@/types/clocking-machine'

library.add(faTabletAlt, faLink, faCopy, faSyncAlt)

const props = defineProps<{
    clockingMachine: ClockingMachine
}>()

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const kioskUrl = ref<string | null>(props.clockingMachine.kiosk_url ?? null)

watch(() => props.clockingMachine.kiosk_url, (value) => {
    kioskUrl.value = value ?? null
})

const openModal = () => {
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
}

const generateLink = async () => {
    isSubmitting.value = true

    try {
        const { data } = await axios.post(
            route('grp.models.clocking-machine.kiosk_token.set', props.clockingMachine.slug),
            { revoke: false }
        )

        kioskUrl.value = data.kiosk_url

        notify({
            title: trans('Success'),
            text: trans('Kiosk link successfully generated'),
            type: 'success',
        })
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to generate the kiosk link, please try again'),
            type: 'error',
        })
    } finally {
        isSubmitting.value = false
    }
}

const copyLink = async () => {
    if (!kioskUrl.value) return

    await navigator.clipboard.writeText(kioskUrl.value)
    notify({
        title: trans('Copied'),
        text: trans('Kiosk link copied to clipboard'),
        type: 'success',
    })
}
</script>

<template>
    <div>
        <Button type="tertiary" size="xs" icon="fal fa-tablet-alt" :tooltip="trans('Tablet link')" @click="openModal" />

        <Modal :isOpen="isModalOpen" width="w-full max-w-lg" @onClose="closeModal">
            <div class="p-1 space-y-4">
                <h2 class="text-2xl font-bold text-center">
                    {{ trans('Kiosk tablet link') }}
                </h2>

                <p class="text-sm text-gray-500 text-center">
                    {{ trans('Open this link on the tablet used at this clocking machine so employees can clock in and out with their PIN.') }}
                </p>

                <div v-if="kioskUrl" class="space-y-2">
                    <div class="flex items-center gap-x-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <FontAwesomeIcon icon="fal fa-link" class="text-gray-400" />
                        <a
                            :href="kioskUrl"
                            target="_blank"
                            rel="noopener"
                            class="primaryLink truncate"
                        >
                            {{ kioskUrl }}
                        </a>
                    </div>

                    <div class="flex justify-end gap-x-2">
                        <Button type="tertiary" icon="fal fa-copy" :label="trans('Copy')" @click="copyLink" />
                        <Button
                            type="tertiary"
                            icon="fal fa-sync-alt"
                            :label="isSubmitting ? trans('Loading') : trans('Regenerate')"
                            :disabled="isSubmitting"
                            :loading="isSubmitting"
                            @click="generateLink"
                        />
                    </div>
                </div>

                <div v-else class="mt-4 flex justify-center">
                    <Button
                        icon="fal fa-tablet-alt"
                        :label="isSubmitting ? trans('Loading') : trans('Generate link')"
                        :disabled="isSubmitting"
                        :loading="isSubmitting"
                        @click="generateLink"
                    />
                </div>

                <div class="mt-8 flex justify-end">
                    <Button type="cancel" @click="closeModal" />
                </div>
            </div>
        </Modal>
    </div>
</template>
