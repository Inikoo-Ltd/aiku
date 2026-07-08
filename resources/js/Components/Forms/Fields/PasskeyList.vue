<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { startRegistration } from '@simplewebauthn/browser'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { routeType } from '@/types/route'

type Passkey = {
    id: number
    name: string
    last_used_at: string | null
}

const props = defineProps<{
    fieldData?: {
        value?: Passkey[]
        deleteRoute?: routeType
    }
}>()

const passkeys = props.fieldData?.value ?? []

const isLoading = ref(false)

const register = async () => {
    isLoading.value = true
    try {
        const response = await fetch(route('grp.profile.passkey.options'))
        const optionsJSON = await response.json()
        console.log('Passkey registration options:', optionsJSON)
        const startRegistrationResponse = await startRegistration({ optionsJSON })
        router.post(route('grp.profile.passkey.store'), {
            passkey: JSON.stringify(startRegistrationResponse),
            options: JSON.stringify(optionsJSON),
        }, {
            onFinish: () => (isLoading.value = false),
        })
    } catch (error) {
        console.error('Passkey registration failed', error)
        isLoading.value = false
    }
}
</script>

<template>
    <div class="space-y-3">
        <div v-if="!passkeys.length" class="text-sm text-gray-500">
            {{ trans('No passkeys registered yet.') }}
        </div>

        <div
            v-for="passkey in passkeys"
            :key="passkey.id"
            class="flex items-center justify-between gap-2 rounded-md border border-gray-200 px-3 py-2">
            <div>
                <div class="text-sm font-medium text-gray-700">{{ passkey.name }}</div>
                <div class="text-xs text-gray-400">
                    {{ passkey.last_used_at ? trans('Last used') + ' ' + passkey.last_used_at : trans('Never used') }}
                </div>
            </div>
            <ButtonWithLink
                icon="far fa-trash-alt"
                label="Delete"
                type="negative"
                :routeTarget="{ ...fieldData?.deleteRoute, parameters: { passkey: passkey.id } }"
            />
        </div>

        <Button
            icon="fal fa-plus"
            :label="trans('Register a passkey')"
            type="secondary"
            :loading="isLoading"
            :disabled="isLoading"
            @click.prevent="register"
        />
    </div>
</template>
