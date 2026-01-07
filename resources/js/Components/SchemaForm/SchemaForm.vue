<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref} from 'vue';
import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus } from "@fas";
import SchemaFileds from '@/Components/SchemaForm/SchemaFileds.vue';
import Button from '../Elements/Buttons/Button.vue';
import { get } from 'lodash-es';

library.add(faPlus);

const props = defineProps({
    /*  form: {
         type: Object,
         default: useForm({}), // Initialize an empty form
         required: false,
     }, */
    blueprint: {
        type: Array, // Change type to Array for blueprint
        default: [], // Initialize with an empty array
        required: true,
    },
    route: {
        type: String,
    }
});

const emits = defineEmits<{
    (e: 'onSuccess', value: string): void
    (e: 'onCancel', value: string): void
    (e: 'onError', value: string): void
    (e: 'onBefore', value: string): void
    (e: 'onStart', value: string): void
    (e: 'onProgress', value: string): void
    (e: 'onFinish', value: string): void
}>()

const setFormValues = () => {
    const initialFormData = {};
    for (const f of props.blueprint) {
        for (const field in f.fields) {
            initialFormData[field] = null;
        }
    }
    return initialFormData
}

const form = useForm(setFormValues())
const loading = ref(false)


const onSubmit = () => {
    if (props.route) {
        form.post(props.route, {
            onBefore: (visit) => { emits('onBefore', visit) },
            onStart: (visit) => { loading.value = true, emits('onStart', visit) },
            onProgress: (progress) => { emits('onProgress', progress) },
            onSuccess: (page) => { emits('onSuccess', page) },
            onError: (errors) => { emits('onError', errors) },
            onFinish: visit => { emits( loading.value = false ,'onFinish', visit) },
        }
        )
    }
}

const onCancel = (e) => {
    emits('onCancel', e);
    form.reset();
}


/* onMounted(() => {
    // Initialize form data with default values from blueprint fields
    if (Object.keys(form.data()).length === 0) {
        const initialFormData = {};
        for (const f of props.blueprint) {
            for (const field in f.fields) {
                initialFormData[field] = null; // Set default value to null, change as needed
            }
        }
        form.setData(initialFormData); // Set initial form data
    }
}); */


</script>

<template>
    <div class="">
        <div v-for="item in blueprint" :key="item.title">
            <div class="mb-3 text-2xl font-medium text-gray-500"><span>{{ item.title }}</span></div>
            <hr class="my-5 h-0.5 border-t-0 bg-neutral-100 dark:bg-white/10" />
            <div class="mb-5 flex flex-wrap">
                <div v-for="(fieldData, fieldName) in item.fields" :key="fieldName"
                    :class="`lg:w-${get(fieldData, 'column', 'full')}  sm:w-full md:w-full px-2`">
                    <SchemaFileds :field="fieldName" :fieldData="fieldData" :form="form" />
                </div>
            </div>
            <div class="flex justify-end">
                <Button @click="onCancel" label="cancel" type="tertiary" class="mr-1" />
                <Button @click="onSubmit" label="Save" type="save" :loading="loading"
                    class="bg-indigo-700 hover:bg-slate-600 border border-slate-500 text-teal-50 focus:outline-hidden focus:ring-2 focus:ring-gray-300 focus:ring-offset-2" />
            </div>
        </div>
    </div>
</template>
