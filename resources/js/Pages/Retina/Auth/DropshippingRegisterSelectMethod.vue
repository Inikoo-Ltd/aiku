
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 16 Jun 2025 15:21:35 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { ref, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { faEnvelope } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBuilding, faGlobe, faPhone, faUser } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { GoogleLogin, decodeCredential  } from 'vue3-google-login'
import RetinaShowIris from "@/Layouts/RetinaShowIris.vue"
import { notify } from "@kyvg/vue3-notification"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

library.add(faEnvelope, faUser, faPhone, faBuilding, faGlobe)

// Set default layout
defineOptions({ layout: RetinaShowIris })

const props = defineProps<{


	registerRoute: {
		name: string,
		parameters: string,
	},
	google: {
        client_id: string
    }
	
}>()

const layout = inject('layout', retinaLayoutStructure)



// Define reactive variables
const isLoading = ref(false)



const isLoadingGoogle = ref(false)
const onCallbackGoogleLogin = (e) => {
    // console.log('xxxxxx Google login callback', e)
    const userData = decodeCredential(e.credential)
    // console.log("zzz Handle the userData", userData)

    // Section: Submit
    router.post(
        route('retina.login_google', {
            shop: layout.website?.id
        }),
        {
            google_credential: e.credential,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingGoogle.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully register"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to login with Google. Please contact administrator."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingGoogle.value = false
            },
        }
    )
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
					label="Register"
				/>


                <!-- Google Login -->
                <div class="mx-auto w-fit">
                    <div class="text-center mb-4 text-sm">
                        {{trans('or use your google account to start registration process')}}
                    </div>

                    <GoogleLogin
                        :clientId="google.client_id"
                        :callback="(e) => onCallbackGoogleLogin(e)"
                        :error="(e) => console.log('yyyyyy error', e)"
                    >
                    
                    </GoogleLogin>
                </div>

                <!-- Registration Link -->
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

