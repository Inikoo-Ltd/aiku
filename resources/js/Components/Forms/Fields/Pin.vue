<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue';
import { notify } from '@kyvg/vue3-notification';
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faSyncAlt, faHistory } from '@fortawesome/free-solid-svg-icons';
import { library } from '@fortawesome/fontawesome-svg-core';
import { routeType } from '@/types/route'
import axios from "axios"
import { get } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
library.add(faSyncAlt);



const props = defineProps<{
    form: any;
    fieldName: string;
    fieldData: {
        required: boolean;
        route_generate: routeType
    };
}>();

const emits = defineEmits()
const originalPin = props.form[props.fieldName]

const historyPin = () => {
    const data = { ...props.form, [props.fieldName]: originalPin }
    emits("update:form", data);
}

const isLoadingGenerate = ref(false)
const generateNewPin = async () => {
    isLoadingGenerate.value = true
    await axios
        .get(route(props.fieldData.route_generate.name, props.fieldData.route_generate.parameters))
        .then((response: any) => {
            props.form[props.fieldName] = response.data.pin;
        });
    isLoadingGenerate.value = false

};

</script>

<template>
    <div class="mb-4 flex space-x-2">
        <Button @click="historyPin" type="tertiary" :icon="faHistory" size="xs" v-tooltip="trans('Undo')" />
        <Button @click="generateNewPin" :label="trans('generate')" :icon="faSyncAlt" size="xs" :loading="isLoadingGenerate" />
    </div>

    <div class="flex  gap-1 flex-wrap rounded-lg" :class="get(form, ['errors', `${fieldName}`]) ? 'errorShake' : ''">
        <template v-if="form[fieldName]">
            <div v-for="(value, index) in Array.from(form[fieldName])" :key="index"
                class="relative w-10 h-10 flex items-center justify-center text-lg font-medium border border-gray-300 rounded-lg bg-white shadow-sm hover:bg-blue-50 transition duration-150">
                <span>{{ value }}</span>
            </div>
        </template>

        <template v-else>
            {{ 'No Pin yet' }}
        </template>
    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>


</template>
