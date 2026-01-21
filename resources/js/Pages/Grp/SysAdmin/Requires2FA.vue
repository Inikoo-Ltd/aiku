<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import LoginPassword from '@/Components/Auth/LoginPassword.vue'
import Checkbox from '@/Components/Checkbox.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import Layout from '@/Layouts/Grp2FA.vue'
import { useLayoutStore } from '@/Stores/layout'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faCopy } from '@fal'
import axios from 'axios'
import PureInput from '@/Components/Pure/PureInput.vue'
defineOptions({ layout: Layout })

const form = useForm({
    one_time_password: '',
    secret_key: '',
})


const isLoading = ref(false)

const submit = () => {
    isLoading.value = true;
    form.secret_key = secretKeyText.value;
    form.post(route('grp.login.validate_save2fa'), {
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

const openModal = ref(false);
const imageXml = ref('');
const secretKey = ref([]);
const secretKeyText = ref('');
const tooltipText = ref(trans('Copy the code'));
const tooltipKey = ref(0);
const tooltipShown = ref(false);
let tooltipTimeout = setTimeout(() => {
    tooltipText.value = trans('Copy the code');
}, 1500);

const copyTextToClipboard = () =>  {
    navigator.clipboard.writeText(secretKey.value.join(''))
        .then(() => {
            tooltipText.value = trans('Copied!')
            tooltipShown.value = true;
            
            clearTimeout(tooltipTimeout);
            tooltipTimeout = setTimeout(() => {
                tooltipText.value = trans('Copy the code');
                tooltipShown.value = false;
                tooltipKey.value++; 
            }, 1500);
        })
        .catch(err => {
            console.error('Failed to copy:', err);
        });
}

const resetSecret = () => {
    imageXml.value = '';
    secretKey.value = [];
    secretKeyText.value = ''
}

const fetch2Fa = async () => {
    if (!imageXml.value || !secretKey.value) {
        if(!openModal.value) openModal.value = true;
        await axios.get((route('grp.profile.2fa-qrcode')))
            .then((response) => {
                imageXml.value = response.data.qrUrl;
                secretKey.value = response.data.secretKey.match(/.{1,4}/g);
                secretKeyText.value = response.data.secretKey;
            })
    }
}

const initialOtp = '';
const isVerified = ref(false);

</script>

<template>

    <Head title="Two Factor Authentication" />
    <div class="space-y-6">
        <div>
            <span for="login" class="block text-sm font-medium text-gray-700">{{ trans("To be able to access the page, you are required to have 2-Factor Authentication") }}</span>
            <div class="mt-3">
                <Button :style="'blue-bk-outline'" @click="openModal = !openModal; fetch2Fa()">
                    {{ trans("Click to enable") }} <LoadingIcon v-if="openModal"/>
                </Button>
            </div>
        </div>
    </div>
    
    <Modal :isOpen="openModal" :zIndex="150" :width="'md:w-[55%] md:max-w-[55%]'">
        <div class="mb-4 max-w-2xl mx-auto">
            <div class="w-full text-center mb-2 text-xl text-balance font-semibold text-red-400">
                {{ trans('Please make sure to save this QR Code on your Authenticator before closing') }}
            </div>
            
            <div class="italic 2xl:col-span-5 text-center text-sm mx-auto opacity-80 w-10/12">
                {{ trans('For your security, do not share this Code and QR to someone else.') }}
            </div>
        </div>

        <div class="relative w-full grid 2xl:grid-cols-5 md:grid-cols-1 gap-y-8">
            <div class="inline-grid 2xl:col-span-2 mx-2">
                <div class="text-center font-semibold mb-2">
                    {{ trans('Scan the QR code with your authenticator app') }}
                </div>
                <div v-if="imageXml" v-html="imageXml" class="mx-auto p-1 border rounded-md border-zinc-600"/>
                <div v-else class="mx-auto h-[360px] w-[360px] p-1 border rounded-md border-zinc-600 skeleton flex">
                    <LoadingIcon class="m-auto"/>
                </div>
            </div>
            
            <div class="flex flex-col 2xl:col-span-3 mx-auto w-full">
                <div class="text-center font-semibold w-full">
                    {{ trans('Or enter this code on your Authenticator App') }}
                </div>
                <div class="flex flex-col justify-items-center items-center px-8 w-full h-full">
                    <div v-if="secretKey.length > 0" class="mx-auto grid grid-cols-4 w-4/5 mt-auto font-semibold p-4 border rounded-md border-zinc-600">
                        <div v-for="charKey in secretKey" class="text-center">
                            {{ charKey }}
                        </div>
                    </div>
                    <div v-else class="mx-auto flex w-4/5 h-[64px] mt-auto font-semibold p-4 border rounded-md border-zinc-600 skeleton">
                        <LoadingIcon class="m-auto"/>
                    </div>
                    <div class="mx-auto w-4/5 font-semibold p-4 mb-auto text-center text-sm">
                        <span
                         v-if="secretKey.length > 0"
                        :key="tooltipKey"
                        class="cursor-pointer py-2 px-3 border rounded-md border-zinc-600 hover:border-zinc-400 text-neutral-950 hover:text-neutral-700 active:text-neutral-950 active:border-zinc-800" 
                        v-tooltip="{
                            content: tooltipText,
                            shown: tooltipShown,
                            triggers: ['hover', 'click']
                        }" 
                        v-on:click="copyTextToClipboard()">
                            {{ trans('Copy Code') }} <FontAwesomeIcon :icon="faCopy" />
                        </span>
                    </div>
                </div>
                <div class="text-center font-semibold w-full px-8">
                    {{ trans('Verify your OTP before you could continue:') }}
                    <div class="w-full flex">
                        <input v-model="form.one_time_password" ref="_inputOneTimePassword" id="one_time_password" name="one_time_password" :autofocus="true"
                            required
                            @keydown.enter="submit"
                            class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                    </div>
                    <ValidationErrors />
                </div>
            </div>

            <!-- <div v-if="props.form.processing" class="bg-black/30 text-white flex items-center justify-center text-6xl absolute inset-0 z-10 rounded-md">
                <LoadingIcon />
            </div> -->
        </div>
        <!-- Section: 2 buttons (cancel & submit) -->
        <div class="grid grid-cols-2 gap-4 mt-8" tabindex="0">
            <Button @click="() => (openModal = false, resetSecret())" label="Cancel" type="negative" full xdisabled="props.form.processing"/>
            <Button @click="() => submit()" xloading="props.form.processing" label="Verify & Continue" :style="'blue-bk-outline'" full icon="fad fa-save"/>
        </div>
    </Modal>

</template>
