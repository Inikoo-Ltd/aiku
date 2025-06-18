
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


const isLoading = ref(false)

const isLoadingGoogle = ref(false)
const onCallbackGoogleLogin = async (e: GoogleLoginResponse) => {

    // Section: Submit
    isLoadingGoogle.value = true
    const data = await axios.post(route('retina.login_google', {}), {
        google_credential: e.credential,
    })

    console.log('Google login response:', data.data)
    if(data.status === 200) {
        if (data.data.logged_in) {
            router.get(route('retina.dashboard.show'))
        } else {
            router.get(route('retina.register_from_google'), {
                google_credential: e.credential
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
	<div class="rounded-md flex items-center justify-center w-full px-4 py-4 lg:px-8">
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
					:loading="isLoading"
					:label="trans('Register')"
				/>

				<div class="text-center text-sm">
					{{trans('or use your google account to start registration process')}}
				</div>

                <!-- Google Login -->
                <div class="mx-auto w-fit">

                    <GoogleLogin
                        :clientId="google.client_id"
                        :callback="(e: GoogleLoginResponse) => onCallbackGoogleLogin(e)"
                        :error="(e: Error) => console.log('error', e)"
                    >
                    
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

