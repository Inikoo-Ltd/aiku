<script setup>
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import Image from '@/Components/Image.vue';

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faSpinnerThird)

const props = defineProps(['form', 'fieldName', 'options'])

const temporaryAvatar = ref(props.form[props.fieldName])

const avatarUploaded = (file) => {
    // Validate that file exists and is a valid Blob/File
    if (!file || !(file instanceof Blob)) {
        return
    }
    
    props.form[props.fieldName] = file
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = (e) => {
        temporaryAvatar.value = {
            original: e.target.result
        }
    }
}

</script>

<template>
    <div class=" w-fit">
        <!-- Avatar Button: Small view -->
        <div class="mt-1 lg:hidden">
            <div class="flex items-center">
                <div class="inline-block h-12 w-12 shrink-0 overflow-hidden rounded-full" aria-hidden="true">
                    <Image id="avatar_mobile" class="h-full w-full rounded-full" :src="temporaryAvatar" alt="" />
                </div>
                <div class="ml-5 rounded-md shadow-sm">
                    <div
                        class="group relative flex items-center justify-center rounded-md border border-gray-300 py-2 px-3 focus-within:ring-2 focus-within:ring-gray-500 focus-within:ring-offset-2 hover:bg-gray-50">
                        <label for="input-avatar-small"
                            class="pointer-events-none relative text-sm font-medium leading-4 text-gray-700">
                            <span>{{ trans("Change") }}</span>
                        </label>
                        <input id="input-avatar-small" name="user-photo" type="file"
                            @input="avatarUploaded($event.target.files[0])"
                            class="absolute h-full w-full cursor-pointer rounded-md border-gray-300 opacity-0" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Avatar Button: Large view -->
        <div class="relative hidden overflow-hidden h-40 aspect-square rounded-full lg:inline-block ring-1 ring-gray-300">
            <Image class="h-full rounded-full" :src="temporaryAvatar" alt="" />
        <!-- <div class="relative hidden h-40 w-40 overflow-hidden rounded-full lg:flex lg:items-center lg:justify-center">
            <img class="relative min-h-full min-w-max" :src="temporaryAvatar" alt="" /> -->
            <label id="input-avatar-large-mask" for="input-avatar-large"
                class="absolute inset-0 flex h-full w-full items-center justify-center bg-black/50 text-sm font-medium text-white opacity-0 hover:opacity-100">
                <span>{{ trans("Change") }}</span>
                <input type="file" @input="avatarUploaded($event.target.files[0])" id="input-avatar-large" name="input-avatar-large" accept="image/*"
                    class="absolute inset-0 h-full w-full cursor-pointer rounded-md border-gray-300 opacity-0" />
            </label>
        </div>

        <!-- Icon: Error, Success, Loading -->
        <div class="absolute top-2 right-0 pr-3 flex items-center pointer-events-none">
            <FontAwesomeIcon v-if="form.errors[fieldName]" icon="fas fa-exclamation-circle" class="h-5 w-5 text-red-500" aria-hidden="true" />
            <FontAwesomeIcon v-if="form.recentlySuccessful" icon="fas fa-check-circle" class="h-5 w-5 text-green-500" aria-hidden="true" />
            <!-- <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third" class="h-5 w-5 animate-spin"/> -->
        </div>

        <div v-if="props.form.errors[props.fieldName]" class="text-red-700">
            {{ props.form.errors[props.fieldName] }}
        </div>
    </div>
</template>


