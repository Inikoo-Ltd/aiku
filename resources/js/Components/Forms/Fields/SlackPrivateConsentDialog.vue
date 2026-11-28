<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ctrans } from '@/Composables/useTrans'
import SlackConsentDetails from '@/Components/Forms/Fields/SlackConsentDetails.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes } from '@fal'

const props = defineProps<{
    consentVersion: string
    termsVersion: string
    startRoute: { name: string }
    unavailableReason?: string | null
}>()

const visible = defineModel<boolean>('visible', { default: false })
const showTerms = ref(false)
const isSending = ref(false)
const error = ref<string | null>(null)

watch(visible, (isOpen) => {
    if (isOpen) {
        showTerms.value = false
        error.value = null
    }
})

const submit = () => {
    router.post(route(props.startRoute.name), {
        understood: true,
        consent_version: props.consentVersion,
        terms_version: props.termsVersion,
    }, {
        preserveScroll: true,
        onStart: () => { isSending.value = true; error.value = null },
        onError: (errors) => { error.value = Object.values(errors)[0] as string },
        onFinish: () => isSending.value = false,
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Connect your Slack')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm text-gray-700">
            <p>{{ ctrans('Your Slack messages come to Verba, and only the people in each conversation can see them.') }}</p>
            <p v-if="unavailableReason" class="text-gray-500">{{ unavailableReason }}</p>
            <p v-if="error" class="text-xs text-red-600" role="alert">{{ error }}</p>
        </div>

        <SlackConsentDetails v-if="showTerms" class="mt-3 max-h-72 overflow-y-auto" />

        <template #footer>
            <div class="flex w-full flex-col gap-3">
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Not now')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Agree and connect')" :loading="isSending" :disabled="isSending || !!unavailableReason" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <div class="text-xs text-gray-500">
                <button type="button" class="underline focus:outline-none" @click="showTerms = !showTerms">{{ ctrans('Privacy policy') }}</button>
            </div>
            </div>
        </template>
    </Dialog>
</template>
