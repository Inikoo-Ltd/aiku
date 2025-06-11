<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { ref, computed, watch, reactive } from "vue";
import { get, set, isNull } from 'lodash-es';
import { faLock } from "@fas";
import { faTimes } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import CornersType from "./CornersType.vue";
library.add(faLock, faTimes);

const props = defineProps<{
    data: any;
    fieldName: string | [];
    options?: any;
    fieldData?: {
        placeholder: string;
        readonly: boolean;
        copyButton: boolean;
    };
    common?: any;
}>();

const emits = defineEmits();

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName);
    } else {
        return data[fieldName];
    }
};

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key];
        return null;
    }, obj);
};
const cornersValue = ref(setFormValue(props.data, props.fieldName));
const section = reactive({});

const cornersSection = ref([
    {
        label: trans("top left"),
        valueForm: get(cornersValue.value, [`topLeft`]),
        id: "topLeft",
    },
    {
        label: trans("top Middle"),
        valueForm: get(cornersValue.value, [`topMiddle`]) || get(cornersValue.value, [`topBottom`]),
        id: "topMiddle",
    },
    {
        label: trans("Top right"),
        valueForm: get(cornersValue.value, [`topRight`]),
        id: "topRight",
    },
    {
        label: trans("bottom left"),
        valueForm: get(cornersValue.value, [`bottomLeft`]),
        id: "bottomLeft",
    },
    {
        label: trans("Bottom Middle"),
        valueForm: get(cornersValue.value, [`bottomMiddle`]),
        id: "bottomMiddle",
    },
    {
        label: trans("Bottom right"),
        valueForm: get(cornersValue.value, [`bottomRight`]),
        id: "bottomRight",
    },
]);

const cornerSideClick = (value) => {
    Object.assign(section, value);
};

watch(section, (newValue) => {
    updateFormValue(newValue);
});

const updateFormValue = (newValue) => {
    const newData = {
        [newValue.id]: newValue.valueForm,
    };
    cornersValue.value = { ...cornersValue.value, ...newData };
    let target = { ...props.data };

    set(target, props.fieldName, cornersValue.value);
    // emits("update:data", target);
};


const clear=(section)=>{
    delete cornersValue.value[section.id]
}

</script>

<template>
    <div class="space-y-8">
        <div class="grid grid-cols-3 gap-0.5 h-full bg-amber-400 border border-gray-300" >
            <div v-for="(cornerSection, index) in cornersSection"
                :key="cornerSection.id"
                class="relative overflow-hidden flex items-center justify-center flex-grow text-base font-semibold py-4"
                :class="[ common &&
                get(common,['corners',cornerSection.id]) &&  !isNull(common.corners[cornerSection.id])
                        ? 'cursor-not-allowed bg-gray-200 text-red-500'
                        : get(section, 'id') == cornerSection.id
                        ? 'bg-amber-300 text-gray-600 cursor-pointer'
                        : 'bg-gray-100 hover:bg-gray-200 text-gray-400 cursor-pointer',
                ]"
                @click="
                    () => {
                        common && get(common,['corners',cornerSection.id])  &&  !isNull(common.corners[cornerSection.id]) ? null : cornerSideClick(cornerSection);
                    }
                "
            >
                <div
                    v-if="
                        common &&
                        get(common,['corners',cornerSection.id]) &&  !isNull(common.corners[cornerSection.id])
                    "
                    class="isolate text-sm italic"
                >
                    <div class="">
                        <font-awesome-icon
                            :icon="['fas', 'lock']"
                            class="mr-2"
                        />
                        Already used in common
                    </div>
                </div>
                <span v-else class="capitalize">{{ cornerSection.label }}</span>
            </div>
        </div>
        <CornersType
            v-if="Object.keys(section).length"
            :section="section"
            :fieldData="fieldData"
            @clear="clear"     
        />
    </div>
</template>
