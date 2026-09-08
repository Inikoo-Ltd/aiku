<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { Passkeys, UserCancelledError, PasskeyExistsError } from '@laravel/passkeys'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTrashAlt } from '@fal'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useFormatTime } from '@/Composables/useFormatTime'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

interface PasskeyItem {
    id: number | string
    name: string
    last_used_at?: string | null
    created_at?: string | null
}

const passkeys = ref<PasskeyItem[]>(props.form[props.fieldName] ?? [])
const isSupported = Passkeys.isSupported()
const newPasskeyName = ref('')
const isAdding = ref(false)
const deletingId = ref<number | string | null>(null)
const errorMessage = ref<string | null>(null)

const addPasskey = async () => {
    isAdding.value = true
    errorMessage.value = null
    try {
        const passkey = await Passkeys.register({ name: newPasskeyName.value || trans('My device') })
        passkeys.value.push(passkey)
        newPasskeyName.value = ''
    } catch (error) {
        if (error instanceof PasskeyExistsError) {
            errorMessage.value = trans('This device already has a passkey for this account')
        } else if (!(error instanceof UserCancelledError)) {
            errorMessage.value = trans('Could not create passkey')
            console.error(error)
        }
    } finally {
        isAdding.value = false
    }
}

const deletePasskey = async (id: number | string) => {
    deletingId.value = id
    errorMessage.value = null
    try {
        await axios.delete(route('grp.passkey.destroy', id))
        passkeys.value = passkeys.value.filter(passkey => passkey.id !== id)
    } catch (error) {
        errorMessage.value = trans('Could not delete passkey')
        console.error(error)
    } finally {
        deletingId.value = null
    }
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="!isSupported" class="text-sm text-gray-500">
            {{ trans('This browser does not support passkeys') }}
        </p>

        <template v-else>
            <ul v-if="passkeys.length" class="divide-y divide-gray-200 border border-gray-200 rounded-md">
                <li v-for="passkey in passkeys" :key="passkey.id" class="flex items-center justify-between px-3 py-2">
                    <div>
                        <div class="text-sm font-medium">{{ passkey.name }}</div>
                        <div v-if="passkey.created_at" class="text-xs text-gray-500">
                            {{ trans('Added') }} {{ useFormatTime(passkey.created_at) }}
                            <template v-if="passkey.last_used_at">
                                &middot; {{ trans('Last used') }} {{ useFormatTime(passkey.last_used_at) }}
                            </template>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-red-500 hover:text-red-700 disabled:opacity-50"
                        :disabled="deletingId === passkey.id"
                        @click="deletePasskey(passkey.id)"
                    >
                        <FontAwesomeIcon :icon="faTrashAlt" fixed-width />
                    </button>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-500">
                {{ trans('No passkeys yet. Add one to sign in without a password.') }}
            </p>

            <div class="flex gap-2">
                <input
                    v-model="newPasskeyName"
                    :placeholder="trans('Passkey name (e.g. My laptop)')"
                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    @keydown.enter.prevent="addPasskey"
                />
                <Button
                    :label="trans('Add passkey')"
                    :loading="isAdding"
                    :disabled="isAdding"
                    @click.prevent="addPasskey"
                />
            </div>

            <p v-if="errorMessage" class="text-sm text-red-500">{{ errorMessage }}</p>
        </template>
    </div>
</template>
