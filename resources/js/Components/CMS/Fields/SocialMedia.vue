<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { cloneDeep } from 'lodash-es'
import PureInput from '@/Components/Pure/PureInput.vue'
import Popover from 'primevue/popover';
import draggable from "vuedraggable";


import { library } from "@fortawesome/fontawesome-svg-core"
import { faShieldAlt, faTimes } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faFacebook} from "@fortawesome/free-brands-svg-icons";

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faShieldAlt, faTimes, faFacebook)

const props = defineProps<{
    modelValue: any,
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', value: {}): void
}>();


const op = ref();
const _addop = ref();
const openIndex = ref<number | null>(null); // Track the currently open disclosure
const icons = [
    {
        type: "Facebook",
        value: "fab fa-facebook",
    },
    {
        type: "Instagram",
        value: "fab fa-instagram",
    },
    {
        type: "Tik Tok",
        value: "fab fa-tiktok",
    },
    {
        type: "Pinterest",
        value: "fab fa-pinterest",
    },
    {
        type: "Youtube",
        value: "fab fa-youtube",
    },
    {
        type: "Linkedin",
        value: "fab fa-linkedin-in",
    },
];

const AddItem = (icon) => {
    let set = cloneDeep(props.modelValue);
    set.push(
        {
            type: icon.type,
            icon: icon.value,
            link: ""
        }
    );
    emits('update:modelValue', set);
    _addop.value.hide();
};

const changeIcon = (icon, data, index) => {
    let set = cloneDeep(props.modelValue);
    set[index] = {
        type: icon.type,
        icon: icon.value,
        link: data.link
    }
    emits('update:modelValue', set);
}

const deleteSocial = (event, index) => {
    event.stopPropagation();
    event.preventDefault();
    let set = cloneDeep(props.modelValue);
    set.splice(index, 1)
    emits('update:modelValue', set);
}

const toggle = (event: any) => {
    op.value.toggle(event);
}

const toggleAdd = (event: any) => {
    _addop.value.toggle(event);
}

const handleDisclosureToggle = (index) => {
    if (openIndex.value === index) {
        openIndex.value = null;
    } else {
        openIndex.value = index;
    }
};
</script>

<template>
    <div class="p-4">
        <draggable :list="props.modelValue" handle=".handle"
            @update:list="(e) => { props.modelValue = e, emits('update:modelValue', props.modelValue) }">
            <template #item="{ element: item, index: index }">
                <div class="grid grid-cols-1 md:cursor-default space-y-2 border-b pb-3 md:border-none">
                    <div class="flex items-center text-xl font-semibold leading-6">
                        <!-- Drag Handle Icon -->
                        <FontAwesomeIcon icon="fal fa-bars" class="handle cursor-grab pr-3 mr-2 text-gray-500" />

                        <div class="relative w-full">
                            <!-- Toggle Button -->
                            <button @click="handleDisclosureToggle(index)"
                                :class="openIndex === index ? 'rounded-t-md' : 'rounded-md'"
                                class="flex w-full justify-between items-center bg-gray-100 px-4 py-2 text-left text-sm font-medium text-gray-700">
                                <span class="font-medium">{{ item.type }}</span>
                                <FontAwesomeIcon :icon="['fas', 'times']"
                                    class="text-red-500 p-1 hover:text-red-600 transition duration-150"
                                    @click.stop="(e) => deleteSocial(e, index)" />
                            </button>

                            <!-- Disclosure Content -->
                            <div v-if="openIndex === index"
                                class="px-4 pb-3 pt-4 text-sm bg-gray-50 rounded-b-md shadow-md">
                                <div class="space-y-4">
                                    <!-- Icon Selection -->
                                    <div>
                                        <span class="block text-xs font-semibold text-gray-500">Icon:</span>
                                        <Button type="dashed" @click="toggle" :full="true"
                                            class="w-full mt-1 text-center bg-white border border-gray-300 rounded-lg shadow-sm">
                                            <FontAwesomeIcon :icon="item.icon" />
                                        </Button>
                                        <Popover ref="op"
                                            class="p-2 bg-white border border-gray-300 rounded-lg shadow-lg">
                                            <div class="grid grid-cols-3 gap-4 p-2">
                                                <div v-for="icon in icons" :key="icon.type"
                                                    @click="() => changeIcon(icon, item, index)"
                                                    class="cursor-pointer flex flex-col items-center p-2 border border-gray-200 rounded-lg hover:bg-gray-100 transition duration-200">
                                                    <FontAwesomeIcon :icon="icon.value"
                                                        class="text-xl mb-1 text-gray-600 hover:text-gray-800 transition duration-150" />
                                                    <span class="text-xs font-medium text-gray-600">{{ icon.type
                                                        }}</span>
                                                </div>
                                            </div>
                                        </Popover>
                                    </div>

                                    <!-- Link Input -->
                                    <div>
                                        <span class="block text-xs font-semibold text-gray-500 mb-2">Link:</span>
                                        <PureInput v-model="item.link" placeholder="Link"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </template>
        </draggable>
        <Button type="dashed" icon="fal fa-plus" label="Add Social Media" full size="s" class="mt-2" @click="toggleAdd" />
        <Popover ref="_addop">
            <div class="grid grid-cols-3 gap-6 p-1">
                <div v-for="icon in icons" :key="icon.type" @click="() => AddItem(icon)"
                    class="cursor-pointer flex flex-col items-center p-4 border border-gray-300 rounded-lg hover:bg-gray-100 transition duration-200">
                    <FontAwesomeIcon :icon="icon.value" class="text-xl mb-2"></FontAwesomeIcon>
                    <span class="text-xs font-medium text-gray-700">{{ icon.type }}</span>
                </div>
            </div>
        </Popover>
    </div>
</template>

<style scoped></style>
