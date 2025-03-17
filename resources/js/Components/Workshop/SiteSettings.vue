<script setup lang="ts">
import { ref, provide } from 'vue'
import { set as setLodash, get, merge, cloneDeep, isObject } from 'lodash-es'
import { getBlueprint } from '@/Composables/getBlueprintWorkshop'
import { getFormValue } from '@/Composables/SideEditorHelper'
import { blueprint } from '@/Components/Workshop/BlueprintSiteSettings'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { Root as RootWebpage } from '@/types/webpageTypes'
import { router } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{ webpage: RootWebpage }>();

const value = ref({
    button: {
        text: { color: null, fontFamily: null },
        background: {
            type: "color",
            color: null,
            image: { original: null }
        },
        padding: {
            unit: "px",
            top: { value: null },
            left: { value: null },
            right: { value: null },
            bottom: { value: null }
        },
        margin: {
            unit: "px",
            top: { value: null },
            left: { value: null },
            right: { value: null },
            bottom: { value: null }
        },
        border: {
            color: null,
            unit: "px",
            rounded: {
                unit: "px",
                topright: { value: null },
                topleft: { value: null },
                bottomright: { value: null },
                bottomleft: { value: null }
            },
            top: { value: null },
            left: { value: null },
            right: { value: null },
            bottom: { value: null }
        }
    }
});

// **1️⃣ Remove null/undefined values from an object**
const cleanObject = (obj: any) => {
    if (!isObject(obj)) return obj;

    return Object.entries(obj).reduce((acc, [key, value]) => {
        if (value !== null && value !== undefined) {
            acc[key] = isObject(value) ? cleanObject(value) : value;
        }
        return acc;
    }, {} as any);
};

// **2️⃣ Process replaceForm fields before merging**
const processReplaceForm = (formList: any[], data: any) => {
    for (const form of formList) {
        if (form.editGlobalStyle) {
            merge(data[form.editGlobalStyle], {
                container: { properties: value.value[form.editGlobalStyle] }
            });
            return;
        }
        if (form.replaceForm) {
            processReplaceForm(form.replaceForm, data);
        }
    }
};

// **3️⃣ Extract values before merging**
const getFormValues = (form: any, data: any = {}) => {
    if (!form || !form.key) return;

    const keyPath = Array.isArray(form.key) ? form.key : [form.key];

    if (form.editGlobalStyle) {
        merge(data[form.editGlobalStyle], {
            container: { properties: value.value[form.editGlobalStyle] }
        });
    } else if (form.replaceForm) {
        processReplaceForm(form.replaceForm, data);
    }
};

// **4️⃣ Final processing & merging**
const onSaveWorkshopFromId = () => {
    for (const web_block of props.webpage.layout.web_blocks) {
        const existingData = cloneDeep(web_block.web_block.layout?.data?.fieldValue || {});

        for (const form of getBlueprint(web_block.type)) {
            getFormValues(form, existingData);
        }

        // **4.1 Clean the new data before merging**
        const cleanedData = cleanObject(existingData);

        // **4.2 Merge only cleaned values to preserve structure**
        merge(web_block.web_block.layout.data.fieldValue, cleanedData);

    }
    debounceSaveWorkshop(props.webpage.layout.web_blocks)
    console.log('Final Data Web Block', props.webpage.layout.web_blocks);
};

provide("onSaveWorkshopFromId", onSaveWorkshopFromId);

const debounceSaveWorkshop = () => {
   /*  const finalBlock = {
        layout: cleanObject(block.web_block.layout),
        show_logged_in: block.visibility.in,
        show_logged_out: block.visibility.out,
        show: block.show,
    }; */

    const data = cloneDeep(props.webpage.layout.web_blocks)
    const finalData = []
 console.log(data)
    for (const weblock of data) {
        finalData.push({
            id:weblock.web_block.id,
            layout: cleanObject(weblock.web_block.layout),
            show_logged_in: weblock.visibility.in,
            show_logged_out: weblock.visibility.out,
            show: weblock.show,
        })
    }

    router.patch(
        route('grp.models.model_has_web_block.bulk.update'),
        { web_blocks : finalData},
        {
            onSuccess: (e) => { },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error",
                });
            },
            preserveScroll: true,
        }
    );
}

</script>

<template>
    <SideEditor v-model="value" :blueprint="blueprint" />
</template>

<style scoped></style>
