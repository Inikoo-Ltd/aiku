
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 16 Jun 2025 15:21:35 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { faEnvelope } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBuilding, faGlobe, faPhone, faUser } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { GoogleLogin  } from 'vue3-google-login'
import RetinaShowIris from "@/Layouts/RetinaShowIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Register from "@/Pages/Retina/Auth/Register.vue";
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

library.add(faEnvelope, faUser, faPhone, faBuilding, faGlobe)

defineOptions({ layout: RetinaShowIris })

defineProps<{


	registerRoute: {
		name: string,
		parameters: string,
	},
	google: {
        client_id: string
    }
	
}>()

interface GoogleLoginResponse {
    credential: string;
}

interface RegisterAccount {
    name: string;
    email: string;
}



const isLoadingGoogle = ref(false)
const onCallbackGoogleLogin = async (e: GoogleLoginResponse) => {

    // Section: Submit
    isLoadingGoogle.value = true
    const data = await axios.post(route('retina.login_google', {}), {
        google_access_token: e.access_token,
    })

    console.log('Google login response:', data.data)
    if(data.status === 200) {
        if (data.data.logged_in) {
            router.get(route('retina.dashboard.show'))
        } else {
            router.get(route('retina.register_from_google'), {
                google_access_token: e.access_token
            }, {
                onStart: () => {
                    isLoadingGoogle.value = true
                }
            })
        }
    } else {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to login with Google. Please contact administrator."),
            type: "error"
        })
    }
}
</script>

<template>
	<div class="rounded-md flex items-center justify-center w-full px-4 py-20 lg:px-8">
        <div class="relative w-full max-w-lg bg-white border border-gray-200 rounded-md shadow-lg px-8 py-10">
            <div v-if="isLoadingGoogle" class="absolute inset-0 bg-black/50 text-white z-10 flex justify-center items-center">
                <LoadingIcon class="text-4xl" />
            </div>

            <form class="flex flex-col gap-y-6">

				<!-- Submit Button -->
				<ButtonWithLink
					:routeTarget="{
						name: 'retina.register_standalone'
					}"
					full
					xlabel="trans('Register')"
				>
                    <template #default="{ isLoadingVisit }">
                        <button
                            aclick.prevent="submit"
                            class="w-full relative flex items-center justify-center gap-2 bg-[#0f172a] disabled:bg-[#393e49] text-white disabled:text-gray-300 hover:bg-black font-normal border border-[#0f172a] rounded-sm px-16 py-2 cursor-pointer transition duration-75 ease-in-out"
                            :disabled="isLoadingVisit"
                        >
                            {{ trans("Register") }}
                            <LoadingIcon v-if="isLoadingVisit" />
                        </button>
                    </template>
                </ButtonWithLink>

                

				<div class="text-center text-sm">
					{{trans('or use your google account to start registration process')}}
				</div>

                <!-- Google Login -->
                <div class="w-full">

                    <GoogleLogin
                        :clientId="google.client_id"
                        popup-type="TOKEN"
                        :callback="(e: GoogleLoginResponse) => onCallbackGoogleLogin(e)"
                        :error="(e: Error) => console.log('error', e)"
                    >
                        <template #default>
                            <div class="w-full relative flex items-center justify-center gap-2 bg-white hover:bg-[#0f172a] font-normal text-gray-800 hover:text-white border border-[#0f172a] rounded-sm px-16 py-2 cursor-pointer transition duration-150 ease-in-out">
                                <div id="google_logo_svg" class="w-5 h-5 absolute left-4">
                                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" class="LgbsSe-Bz112c"><g><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path><path fill="none" d="M0 0h48v48H0z"></path></g></svg>
                                </div>
                                {{ trans("Register with Google") }}
                            </div>
                        </template>
                    </GoogleLogin>
                </div>

                <div class="border-t border-gray-200 flex justify-center items-center mt-2 pt-4">
                    <p class="text-sm text-gray-500">
                        <span class="font-normal">{{ trans("Already have an account?") }}</span>
                        <Link :href="route('retina.login.show')"
                            class="  font-medium hover:underline transition duration-150 ease-in-out ml-1">
                            {{ trans("Login here") }}
                        </Link>
                    </p>
                </div>
            </form>
        </div>



    </div>
	

</template>


<style scoped lang="scss">
:deep(.g-btn-wrapper) {
    width: 100%;
}
</style>