<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref, computed } from "vue"
import { get, set, isNull, cloneDeep } from "lodash-es"
import { faLock } from "@fas"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import CornersType from "./CornersType.vue"

library.add(faLock, faTimes)

const props = defineProps<{
    modelValue: any
    fieldName: string | string[]
    options?: any
    fieldData?: {
        placeholder: string;
        readonly: boolean;
        copyButton: boolean;
    };
    common?: any;
}>();

const emits = defineEmits(["update:modelValue"])


const getNestedValue = (obj: any, keys: string[]) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

const getFormValue = (data: any, fieldName: string | string[]) => {
    if (Array.isArray(fieldName)) return getNestedValue(data, fieldName)
    return data?.[fieldName]
}


const cornersValue = computed({
    get() {
        return props.modelValue
    },
    set(val) {
        console.log('finalData to emit:', val)
        emits("update:modelValue", val)
    }
})



const section = ref<any>(null)


const cornersSection = ref([
    {
        label: trans("Top left"),
        valueForm: get(cornersValue.value, [`topLeft`]),
        id: "topLeft",
    },
    {
        label: trans("Top middle"),
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

const cornerSideClick = (value: any) => {
    section.value = cloneDeep(value)
}

const updateFormValue = (newValue: any) => {
    if(!section.value) return;
    const newCorners = {
        ...cornersValue.value,
        [section.value.id]: newValue,
    }
    cornersValue.value = newCorners
}

const clear = (sec: any) => {
    const newCorners = cloneDeep(cornersValue.value)
    delete newCorners[section.value.id]

    cornersValue.value = newCorners
    section.value = null
}

</script>

<template>
    <div class="space-y-6">
        <!-- grid container -->
        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
            <div class="grid grid-cols-3 gap-2">
                <div v-for="cornerSection in cornersSection" :key="cornerSection.id"
                    class="relative flex items-center justify-center rounded-lg border text-sm font-medium h-20 transition-all duration-150 select-none"
                    :class="[
                        common &&
                            get(common, ['corners', cornerSection.id]) &&
                            !isNull(common.corners[cornerSection.id])
                            ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed opacity-70'
                            : get(section, 'id') === cornerSection.id
                                ? 'bg-amber-100 border-amber-400 text-amber-700 shadow-inner'
                                : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-amber-50 hover:border-amber-300 hover:text-gray-700 cursor-pointer'
                    ]" @click="
            () => {
                common &&
                    get(common, ['corners', cornerSection.id]) &&
                    !isNull(common.corners[cornerSection.id])
                    ? null
                    : cornerSideClick(cornerSection)
            }
        ">
                    <!-- locked -->
                    <div v-if="
                        common &&
                        get(common, ['corners', cornerSection.id]) &&
                        !isNull(common.corners[cornerSection.id])
                    " class="flex flex-col items-center gap-1 text-xs">
                        <font-awesome-icon :icon="['fas', 'lock']" class="text-gray-400" />
                        <span class="italic">Used in common</span>
                    </div>

                    <!-- label -->
                    <span v-else class="capitalize tracking-wide">
                        {{ cornerSection.label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- editor -->
        <div v-if="section" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <CornersType 
                :modelValue="modelValue[section.id]" 
                :fieldData="fieldData" 
                @update:modelValue="updateFormValue" 
                @clear="clear" 
            />
        </div>
    </div>
</template>
