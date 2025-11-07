<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

import Layout from '@/Layouts/GrpAuth.vue'
import { useLayoutStore } from '@/Stores/layout'
defineOptions({ layout: Layout })

const form = useForm({
    username: '',
    password: '',
    remember: false,
})


const isLoading = ref(false)

const submit = () => {
    isLoading.value = true
    form.post(route('grp.login.show'), {
        onError: () => (
            isLoading.value = false
        ),
        onFinish: () => {
            console.log('Org length', useLayoutStore().organisations.data.length)
            // useLayoutStore().organisations.data.length === 1
            //     ? router.get(route('grp.org.dashboard.show', useLayoutStore().organisations.data[0].slug))
            //     : false
        },
        onSuccess: () => {
            form.reset('password')
        }
    })
}

const _inputUsername = ref(null)

onMounted(async () => {
    _inputUsername.value?.focus()
})


</script>

<template>

    <Head title="Login" />
    <form class="space-y-6" @submit.prevent="submit">
        <div>
            <label for="login" class="block text-sm font-medium text-gray-700">{{ trans('Username') }}</label>
            <div class="mt-1">
                <input v-model="form.username" ref="_inputUsername" id="username" name="username" :autofocus="true"
                    autocomplete="username" required
                    placeholder="johndoe"
                    @keydown.enter="submit"
                    class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
            </div>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700"> {{ trans('Password') }} </label>
            <div class="mt-1 flex flex-col rounded-md">
                <LoginPassword @keydown.enter="submit" :showProcessing="false" id="password" name="password" :form=form fieldName='password' placeholder="********" />
                <Link href="/resetpassword" class="w-fit text-xs mt-2 italic text-gray-600 hover:underline cursor-pointer">Forgot password?</Link>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <Checkbox name="remember-me" id="remember-me" v-model:checked="form.remember" />
                <label for="remember-me" class="ml-2 block text-sm select-none"> {{ trans('Remember me') }} </label>
            </div>
        </div>

        <div class="space-y-2">
            <Button full @click.prevent="submit" :loading="isLoading" label="Sign in" type="indigo"/>
        </div>
    </form>

    <ValidationErrors />

</template>
