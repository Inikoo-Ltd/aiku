<script setup lang="ts">
import { ref, watch } from 'vue'
import { Switch } from '@headlessui/vue'
import { isNull, get } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faCheck } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import Modal from '@/Components/Utils/Modal.vue'
import axios from 'axios'
import { faCopy } from '@fal'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import { useForm } from '@inertiajs/vue3'
library.add(faTimes, faCheck)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        noIcon?: boolean
        suffixImage?: string
    }
    submit: Function
}>()

const emits = defineEmits()

const updateFormValue = (newValue) => {
    let target = props.form
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue)
    } else {
        target[props.fieldName] = newValue
    }
    emits("update:form", target,newValue)
}

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        if (isNull(data[fieldName]) || data[fieldName] == ''){
            updateFormValue(false)
            return false
        } 
        else{
            return data[fieldName]
        } 
       
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return false
    }, obj)
}

const value = ref(setFormValue(props.form, props.fieldName))

watch(value, (newValue) => {
    // Update the form field value when the value ref changes
    updateFormValue(newValue)
    props.form.errors[props.fieldName] = ''
}, {
    deep: true
})

const openModal = ref(false);

// props.fieldValue.has2fa
const imageXml = ref('');
const secretKey = ref([]);
const tooltipText = ref(trans('Copy the code'));
const tooltipKey = ref(0);
const tooltipShown = ref(false);
let tooltipTimeout = setTimeout(() => {
    tooltipText.value = trans('Copy the code');
}, 1500);;
let initialValue = value.value.has_2fa;
const errorsMsg = ref('');

const form = useForm({
    one_time_password: '',
    secret_key: '',
})

const validateSave = async () => {
    // Do nothing if authenticated already
    if(initialValue){
        return;
    }
    form.secret_key = value.value.secretKey;
    form.one_time_password = value.value.one_time_password;
    form.post(route('grp.login.validate_save2fa'))
}

const fetch2Fa = async () => {
    if (!imageXml.value || !secretKey.value) {
        if(!openModal.value) openModal.value = true;
        await axios.get((route('grp.profile.2fa-qrcode')))
            .then((response) => {
                imageXml.value = response.data.qrUrl;
                secretKey.value = response.data.secretKey.match(/.{1,4}/g);
                value.value.secretKey = response.data.secretKey;
            })
    }
}

const resetSecret = () => {
    imageXml.value = '';
    secretKey.value = [];
    value.secretKey = null;
    errorsMsg.value = '';
}

const resetSwitch = (val: any) => {
    if(val) {
        fetch2Fa()
    } else {
        resetSecret();
        if(initialValue){
            props.form.processing = true;
            props.submit().then(() => {
                props.form.processing = false
            });
        }
    }
}

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

</script>
<template>
    <div>
        <Switch
            v-model="value.has_2fa"
            @update:modelValue="(e) => resetSwitch(e)"
            class="pr-1 relative inline-flex h-6 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
            :class="[
                value.has_2fa ? 'bg-indigo-500' : 'bg-indigo-100',
                form.errors[fieldName] ? 'errorShake' : ''
            ]" 
        >
            <span aria-hidden="true" :class="value.has_2fa ? 'translate-x-6 bg-white ' : 'translate-x-0 bg-gray-50'"
                class="flex items-center justify-center pointer-events-none h-full w-1/2 transform rounded-full shadow-lg ring-0 transition">
                <template v-if="!fieldData.noIcon">
                    <FontAwesomeIcon v-if="value.has_2fa" icon='fal fa-check' class='text-sm text-green-500' fixed-width aria-hidden='true' />
                    <FontAwesomeIcon v-else icon='fal fa-times' class='text-sm text-red-500' fixed-width aria-hidden='true' />
                </template>
            </span>
        </Switch>
        <slot v-if="fieldData.suffixImage" name="suffix-image">
            <img :src="fieldData.suffixImage" class="inline-block h-8 w-8 ml-2  object-cover" />
        </slot>
        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
        <div class="w-full flex mt-2" v-if="value.has_2fa">
            <span class="text-xs underline cursor-pointer text-gray-600" v-on:click="() => {fetch2Fa(); openModal = true;}"> 
                {{ trans('Manage your Two Factor Authentication') }}
            </span>
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
                    <div v-if="!initialValue" class="text-center font-semibold w-full px-8">
                        {{ trans('Verify your OTP before you could continue:') }}
                        <div class="w-full flex">
                            <input v-model="value.one_time_password" :autofocus="true" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" :class="errorsMsg ? 'ring-1 ring-red-500' : ''"/>
                        </div>
                        <ValidationErrors />
                        <div v-if="errorsMsg" class="text-red-500 text-sm italic">
                            {{ errorsMsg }}
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="props.form.processing" class="bg-black/30 text-white flex items-center justify-center text-6xl absolute inset-0 z-10 rounded-md">
                <LoadingIcon />
            </div>
        </div>

        <!-- Section: 2 buttons (cancel & submit) -->
        <div class="grid grid-cols-2 gap-4 mt-8" tabindex="0">
            <Button @click="() => (openModal = false, value.has_2fa = initialValue, resetSecret())" label="Cancel" type="negative" full :disabled="props.form.processing"/>
            <Button @click="() => validateSave()" :loading="props.form.processing" label="Continue" full icon="fad fa-save" />
        </div>
    </Modal>
</template>