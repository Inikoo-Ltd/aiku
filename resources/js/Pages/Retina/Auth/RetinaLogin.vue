<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref, nextTick, inject } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import RetinaShowIris from '@/Layouts/RetinaShowIris.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Background from '@/Components/CMS/Fields/Background.vue'
import { GoogleLogin, decodeCredential  } from 'vue3-google-login'
import { notify } from '@kyvg/vue3-notification'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import axios from 'axios'
import Modal from '@/Components/Utils/Modal.vue'

defineOptions({ layout: RetinaShowIris })
const props = defineProps<{
    google: {
        client_id: string
    }
}>()
const form = useForm({
    username: '',
    password: '',
    remember: false,
})

const layout = inject('layout', retinaLayoutStructure)

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
    // console.log('ff', inputUsername.value?._inputRef)
    inputUsername.value?._inputRef?.focus()
})

const registerAccount = ref(null)
const isOpenModalRegistration = ref(false)
const isLoadingGoogle = ref(false)
const onCallbackGoogleLogin = async (e) => {
    // console.log('xxxxxx Google login callback', e)
    const userData = decodeCredential(e.credential)
    // console.log("zzz Handle the userData", userData)

    // Section: Submit
    isLoadingGoogle.value = true
    const data = await  axios.post(route('retina.login_google', {
        shop: layout.website?.id
    }), {
        google_credential: e.credential,
    })

    if(data.status === 200) {
        notify({
            title: trans("Success"),
            text: trans("Successfully login"),
            type: "success"
        })

        if ('not registered yes') {
            isOpenModalRegistration.value = true
            registerAccount.value = data.response?.data
        } else {

        }

    } else {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to login with Google. Please contact administrator."),
            type: "error"
        })
    }

    isLoadingGoogle.value = false
    

    // router.post(
    //     route('retina.login_google', {
    //         shop: layout.website?.id
    //     }),
    //     {
    //         google_credential: e.credential,
    //     },
    //     {
    //         preserveScroll: true,
    //         preserveState: true,
    //         onStart: () => {
    //             isLoadingGoogle.value = true
    //         },
    //         onSuccess: () => {
    //             notify({
    //                 title: trans("Success"),
    //                 text: trans("Successfully login"),
    //                 type: "success"
    //             })
    //         },
    //         onError: errors => {
    //             notify({
    //                 title: trans("Something went wrong"),
    //                 text: trans("Failed to login with Google. Please contact administrator."),
    //                 type: "error"
    //             })
    //         },
    //         onFinish: () => {
    //             isLoadingGoogle.value = false
    //         },
    //     }
    // )
}
</script>

<template>

    <Head title="Login" />

    <div class="rounded-md flex items-center justify-center w-full px-6 py-12 lg:px-8">
        <div class="relative w-full max-w-lg bg-transparent px-4 py-3">
            <div v-if="isLoadingGoogle" class="absolute inset-0 bg-black/50 text-white z-10 flex justify-center items-center">
                <LoadingIcon class="text-4xl" />
            </div>

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

                <ValidationErrors />

                <!-- Submit Button -->
                <div class="space-y-2">
                    <Button full @click.prevent="submit" :loading="isLoading" label="Sign in" :type="'tertiary'" :class="'!bg-[#C1A027] !text-white'" />
                </div>

                <!-- Google Login -->
                <div v-if="layout?.iris?.website?.type !== 'fulfilment'" class="mx-auto w-fit">
                    <div class="text-center mb-4 text-sm">
                        Or
                    </div>

                    <GoogleLogin
                        :clientId="google?.client_id"
                        :callback="(e) => onCallbackGoogleLogin(e)"
                        :error="(e) => console.log('yyyyyy error', e)"
                    >

                    </GoogleLogin>
                </div>

                <!-- Registration Link -->
                <div class="border-t border-gray-200 flex justify-center items-center mt-2 pt-4">
                    <p class="text-sm text-gray-500">
                        {{ trans("Don\'t have an account") }}?
                        <Link :href="route('retina.register')"
                            class="  font-medium hover:underline transition duration-150 ease-in-out ml-1">
                            {{ trans("Register here") }}
                        </Link>
                    </p>
                </div>
            </form>
        </div>
    </div>
</template>
