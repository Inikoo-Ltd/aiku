<script setup lang="ts">
import { ref, watch } from "vue";
import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Button from "@/Components/Elements/Buttons/Button.vue";
import OverviewProperty from "@/Components/Workshop/Properties/OverviewProperty.vue";

import { library } from "@fortawesome/fontawesome-svg-core";
import { faPlus, faTrash } from "@fal";
import { cloneDeep } from "lodash-es";

library.add(faPlus, faTrash);

// Define reactive modelValue prop
const props = defineProps({
    modelValue: {
        type: Array,
        required: true,
    },
    type : {
        type: String,
        default : 'text'
    }
});

// Emit changes to modelValue
const emit = defineEmits(["update:modelValue"]);

const createText = () => {
    emit("update:modelValue", [
        ...props.modelValue,
        {
            text: "<h2>New Text</h2>",
            properties: {
                width: "400px",
                height: "200px",
                position: {
                    top: "-5px",
                    left: "532px",
                    right: "8.33299999999997px",
                    bottom: "152.812px",
                },
            },
        },
    ]);
};

const createImage = () => {
    emit("update:modelValue", [
        ...props.modelValue,
        {
            sources: {
                avif: "http://10.0.0.100:8080/2bm_AyX-KHViYhTBhS2z9_W6Ep0Pzz_eXjP26ClWQ-8/bG9jYWw6Ly8vYWlrdS9hcHAvbWVkaWEvbWVkaWEvMjc2MTUvYmU0MTQ3Nzk5NGI2MDEwM2NkZDI5NzUyNmQxYmM4MDguanBn.avif",
                webp: "http://10.0.0.100:8080/_r_0qxma2l01eS-xcmJB7WNqKNYEnTEWJ3GrnAVSX0s/bG9jYWw6Ly8vYWlrdS9hcHAvbWVkaWEvbWVkaWEvMjc2MTUvYmU0MTQ3Nzk5NGI2MDEwM2NkZDI5NzUyNmQxYmM4MDguanBn.webp",
                original: "http://10.0.0.100:8080/5GYU0tMJNtINwmg559U7ADtCTmKeA-AAa5UDwYG_GGA/bG9jYWw6Ly8vYWlrdS9hcHAvbWVkaWEvbWVkaWEvMjc2MTUvYmU0MTQ3Nzk5NGI2MDEwM2NkZDI5NzUyNmQxYmM4MDguanBn"
            },
            properties: {
                width: "491px",
                height: "819px",
                position: {
                    top: "-5px",
                    left: "532px",
                    right: "8.33299999999997px",
                    bottom: "152.812px",
                }
            }
        }
    ]);
};

const deleteText = (event, index) => {
    event.stopPropagation();
    const updatedModel = [...props.modelValue];
    updatedModel.splice(index, 1);
    emit("update:modelValue", updatedModel);
};


const onChangeProperty = (index, data) => {
    const setData = cloneDeep(props.modelValue)
    setData[index].properties = data
    emit("update:modelValue", setData);
}


</script>

<template>
    <div>
        <div v-for="(field, index) in modelValue" :key="index">
            <Disclosure v-slot="{ open }">
                <DisclosureButton
                    class="flex w-full mb-1 justify-between bg-gray-100 px-4 py-2 text-left text-sm font-medium text-gray-900 hover:bg-gray-200 focus:outline-hidden focus-visible:ring-3 focus-visible:ring-gray-500/75">
                    <span>{{ type == 'text' ? 'Text' : 'Image' }} {{ index + 1 }}</span>
                    <FontAwesomeIcon :icon="faTrash" class="text-red-500"
                        @click="(event) => deleteText(event, index)" />
                </DisclosureButton>
                <DisclosurePanel class="px-4 pb-2 pt-4 text-sm text-gray-500">
                    <OverviewProperty v-model="modelValue[index].properties"
                        @update:model-value="(data) => onChangeProperty(index, data)"></OverviewProperty>
                </DisclosurePanel>
            </Disclosure>
        </div>
        <div class="my-2">
            <Button type="dashed" label="create" :icon="faPlus" full @click="()=>type == 'text' ? createText() : createImage()"></Button>
        </div>
    </div>
</template>

<style scoped></style>
