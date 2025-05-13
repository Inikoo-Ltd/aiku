<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref, nextTick } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LayoutRetinaAuth from '@/Layouts/RetinaShowIris.vue'
import PureInput from '@/Components/Pure/PureInput.vue'

defineOptions({ layout: LayoutRetinaAuth })
const form = useForm({
    username: '',
    password: '',
    remember: false,
})

const isLoading = ref(false)

const submit = () => {
    isLoading.value = true
    form.post(route('retina.login.store', {
        ref: route().params?.['ref']
    }), {
        onError: () => isLoading.value = false,
        onFinish: () => form.reset('password'),
    })
}

const inputUsername = ref(null)

onMounted(async () => {
    await nextTick()
    console.log('ff', inputUsername.value?._inputRef)
    inputUsername.value?._inputRef?.focus()
})


</script>

<template>

    <Head title="Login" />

    <div class="rounded-md flex items-center justify-center w-full px-6 py-12 lg:px-8">
        <div class="w-full max-w-sm bg-transparent">
            <form class="space-y-6">
                <!-- Username Field -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">
                        {{ trans('Username or Email') }}
                    </label>
                    <div class="mt-1">
                        <PureInput v-model="form.username" ref="inputUsername" id="username" name="username"
                            :autofocus="true" autocomplete="username" required placeholder="username"
                            @keydown.enter="submit" />
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        {{ trans('Password') }}
                    </label>
                    <div class="mt-1">
                        <LoginPassword :showProcessing="false" id="password" name="password" :form="form"
                            fieldName="password" @keydown.enter="submit" placeholder="********" />
                        <div class="flex justify-between mt-2">
                            <div class="flex items-center justify-between cursor-pointer">
                                <Checkbox name="remember-me" id="remember-me" v-model:checked="form.remember" />
                                <label for="remember-me" class="ml-2 block text-sm select-none cursor-pointer"> {{ trans('Remember me') }} </label>
                            </div>

                            <Link :href="route('retina.reset-password.edit')"
                                class="text-sm   font-medium hover:underline transition duration-150 ease-in-out">
                                {{ trans("Forgot password?") }}
                            </Link>
                        </div>
                    </div>
                </div>



                <!-- Submit Button -->
                <div class="space-y-2">
                    <Button full @click.prevent="submit" :loading="isLoading" label="Sign in" type="primary"/>
                </div>

                <!-- Registration Link -->
                <div class="flex justify-center items-center mt-4">
                    <p class="text-sm text-gray-500">
                        {{ trans("Don\'t have an account") }}?
                        <Link :href="route('retina.register')"
                            class="  font-medium hover:underline transition duration-150 ease-in-out ml-1">
                            {{ trans("Register here") }}
                        </Link>
                    </p>
                </div>
            </form>
            <ValidationErrors />
        </div>
    </div>
</template>
