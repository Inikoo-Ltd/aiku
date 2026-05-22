<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { inject, onMounted, onBeforeUnmount, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

import Layout from '@/Layouts/Grp2FA.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useLogoutAuth } from '@/Composables/useAppMethod'
import { faSignOutAlt } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
defineOptions({ layout: Layout })

const form = useForm({
    one_time_password: '',
})


const submit = () => {
    if (form.processing) {
        return
    }

    form.post(route('grp.login.auth2fa'), {
        onSuccess: () => {
            form.reset('one_time_password')
        }
    })
}

const _inputOneTimePassword = ref<HTMLInputElement | null>(null)

const hasFocused = ref(false)

const focusOtpOnReturn = () => {
    if (document.visibilityState === 'visible') {
        _inputOneTimePassword.value?.focus()
    }
}

onMounted(() => {
    if (!hasFocused.value && _inputOneTimePassword.value) {
        _inputOneTimePassword.value.focus()
        hasFocused.value = true
    }

    document.addEventListener('visibilitychange', focusOtpOnReturn)
})

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', focusOtpOnReturn)
})

const layout = inject("layout", layoutStructure)

const isLoadingLogout = ref(false);

const onLogoutAuth = () => {
    useLogoutAuth(layout.user, {
        onStart: () => (isLoadingLogout.value = true),
        onError: () => (isLoadingLogout.value = false),
    })
}
</script>

<template>
    <Head title="Two Factor Authentication" />
    <form class="relative z-10 space-y-6" @submit.prevent="submit">
        <div>
            <label class="block text-sm font-medium text-gray-700">
                {{ trans('Enter OTP from your Authenticator App') }}
            </label>

            <input v-model="form.one_time_password" ref="_inputOneTimePassword" id="one_time_password" required
                class="mt-1 block w-full px-3 py-2 border rounded-md"   :type="'text'" />
        </div>

        <div class="flex space-x-2">
            <Button full @click.prevent="submit" :loading="form.processing" :disabled="form.processing" label="Enter" type="indigo"/>
            <Button :type="'red-r-outline'" :injectStyle="['margin-top:unset', 'margin-left:8px']" @click="onLogoutAuth()">
                <FontAwesomeIcon :icon="faSignOutAlt" />
                <LoadingIcon v-if="isLoadingLogout"/>
            </Button>
        </div>
    </form>
    <ValidationErrors />
</template>
