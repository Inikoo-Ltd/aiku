<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref, nextTick } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import RetinaShowIris from '@/Layouts/RetinaShowIris.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { GoogleLogin  } from 'vue3-google-login'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import axios from 'axios'
import Modal from '@/Components/Utils/Modal.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'

defineOptions({ layout: RetinaShowIris })

defineProps<{
    google: {
        client_id: string
    }
}>()
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
    inputUsername.value?._inputRef?.focus()
})

interface GoogleLoginResponse {
    credential: string;
}

const registerAccount = ref(null)
const isOpenModalRegistration = ref(false)
const isLoadingGoogle = ref(false)
const onCallbackGoogleLogin = async (e: GoogleLoginResponse) => {

    // Section: Submit
    isLoadingGoogle.value = true
    const data = await  axios.post(route('retina.login_google', {}), {
        google_access_token: e.access_token,
    })

    console.log('Google login response:', data.data)
    if(data.status === 200) {


        if (data.data.logged_in) {


            router.get(route('retina.dashboard.show'))
        } else {
            isOpenModalRegistration.value = true
            registerAccount.value = data.data.google_user
        }

    } else {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to login with Google. Please contact administrator."),
            type: "error"
        })
    }

    isLoadingGoogle.value = false

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

                <div class="text-center mb-4 text-sm">
                    {{trans('or use your Google account to login')}}
                </div>

                <!-- Google Login -->
                <div class="mx-auto w-fit">

                    <GoogleLogin
                        :clientId="google?.client_id"
                        popup-type="TOKEN"
                        :callback="(e: GoogleLoginResponse) => onCallbackGoogleLogin(e)"
                        :error="(e: Error) => console.log('error', e)"
                    >
                        <template #default>
                            <div class="relative flex items-center justify-center gap-2 bg-white hover:bg-[#0f172a] font-normal text-gray-800 hover:text-white border border-[#0f172a] rounded-sm px-16 py-2 cursor-pointer transition duration-150 ease-in-out">
                                <div id="google_logo_svg" class="w-5 h-5 absolute left-4">
                                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="LgbsSe-Bz112c"><g><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path><path fill="none" d="M0 0h48v48H0z"></path></g></svg>
                                </div>
                                {{ trans("Login with Google") }}
                            </div>
                        </template>
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

        
		<Modal :isOpen="isOpenModalRegistration" @close="isOpenModalRegistration = false" width="max-w-2xl w-full">
			<div class="p-6">
				<h2 class="text-lg mb-2 text-center">
					Hello, <span class="font-semibold">{{ registerAccount?.name }}</span>!
				</h2>

				<div class="text-gray-600 mb-4 text-center">
					<div class="italic mb-3">{{ registerAccount?.email }}</div>
                    <p>{{trans('This email was not found in our database')}}</p>
					<p>{{trans('Do you want to create an account?')}}</p>
				</div>

				<div class="flex gap-x-2">
					<Button @click="() => isOpenModalRegistration = false" :label="trans('No, thanks')" type="tertiary" />
                    <ButtonWithLink
                        :routeTarget="{
                            name: 'retina.register_from_google',
                            parameters: {
                                google_access_token: registerAccount?.google_access_token
                            }
                        }"
                        :label="trans('Yes')"
                        full
                        class="!bg-[#C1A027] !text-white"
                    />
				</div>
			</div>

		</Modal>
    </div>
</template>
