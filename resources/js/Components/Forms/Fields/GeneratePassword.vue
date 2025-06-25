<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->


<script setup lang="ts">
import { ref } from "vue";
import Password from "@//Components/Forms/Fields/Password.vue";
import { useCopyText } from "@/Composables/useCopyText";

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faCheckCircle, faExclamationCircle, faEye, faEyeSlash } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faSync } from "@far";
import { faCopy } from "@fal";

library.add(faExclamationCircle, faCheckCircle, faEye, faEyeSlash, faCopy)

const props = defineProps(['form', 'fieldName', 'options', 'fieldData'])


const emits = defineEmits()
const _password = ref()


const isRecentlyCopied = ref(false)
const onClickCopyButton = async (text: string) => {
    useCopyText(text)
    isRecentlyCopied.value = true
    setTimeout(() => {
        isRecentlyCopied.value = false
    }, 2000)
}



const generatePassword = () => {
  let target = props.form;

  // Generate a random 8-character password
  const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghjkmnopqrstuvwxyz023456789';
  let password = '';
  for (let i = 0; i < 8; i++) {
    const randomIndex = Math.floor(Math.random() * characters.length);
    password += characters.charAt(randomIndex);
  }

  target[props.fieldName] = password;
  emits("update:form", target);
  props.form.clearErrors();
  if (_password.value) _password.value.showPassword = false; 
};

</script>

<template>
    <div class="relative rounded-md" :class="form.errors[fieldName] ? 'errorShake' : ''">


            <div class="flex items-center gap-2 relative">
                <!-- Password Field -->
                 <div class="w-full">
                    <Password ref="_password" :form="form" :fieldName="fieldName" :options="options" :fieldData="fieldData"
                    class="w-ful" />
                 </div>


                <!-- Copy Button Slot -->
                <slot v-if="form[fieldName]?.length" name="copyButton">
                    <div class="group flex justify-center items-center absolute inset-y-0 right-12 gap-x-1">
                        <Transition name="spin-to-down">
                            <!-- Check Icon (Recently Copied) -->
                            <FontAwesomeIcon v-if="isRecentlyCopied" icon="fal fa-check"
                                class="text-green-500 px-3 h-full text-xs leading-none" fixed-width
                                aria-hidden="true" />

                            <!-- Copy Icon -->
                            <FontAwesomeIcon v-else @click="() => onClickCopyButton(form[fieldName])" icon="fal fa-copy"
                                class="px-3 h-full text-xs leading-none opacity-20 group-hover:opacity-75 group-active:opacity-100 cursor-pointer"
                                fixed-width aria-hidden="true" />
                        </Transition>
                    </div>
                </slot>
            </div>

        <!-- Generate Button -->
        <div v-if="fieldData?.use_generate_password !== false"
        type="button"
            class="mt-4 text-sm bg-gray-200 inline-block select-none rounded-xs border w-full py-2 text-center hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 cursor-pointer"
            @click="generatePassword">
            <FontAwesomeIcon class="mr-1" :icon="faSync" /> Generate
        </div>
    </div>
</template>
