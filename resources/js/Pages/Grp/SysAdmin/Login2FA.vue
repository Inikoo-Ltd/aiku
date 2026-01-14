<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

import Layout from '@/Layouts/Grp2FA.vue'
import { useLayoutStore } from '@/Stores/layout'
defineOptions({ layout: Layout })

const form = useForm({
    one_time_password: '',
})


const isLoading = ref(false)

const submit = () => {
    isLoading.value = true
    form.post(route('grp.login.auth2fa'), {
        onError: () => (
            isLoading.value = false
        ),
        onFinish: () => {
            console.log('Org length', useLayoutStore().organisations.data.length)
        },
        onSuccess: () => {
            form.reset('password')
        }
    })
}

const _inputOneTimePassword = ref(null)

onMounted(async () => {
    _inputOneTimePassword.value?.focus()
})
</script>

<template>

    <Head title="Two Factor Authentication" />
    <form class="space-y-6" @submit.prevent="submit">
        <div>
            <label for="login" class="block text-sm font-medium text-gray-700">{{ trans('Enter OTP from your Authenticator App') }}</label>
            <div class="mt-1">
                <input v-model="form.one_time_password" ref="_inputOneTimePassword" id="one_time_password" name="one_time_password" :autofocus="true"
                    required
                    @keydown.enter="submit"
                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
            </div>
        </div>
        <div class="space-y-2">
            <Button full @click.prevent="submit"  label="Enter" type="indigo"/>
        </div>
    </form>

    <ValidationErrors />

</template>
