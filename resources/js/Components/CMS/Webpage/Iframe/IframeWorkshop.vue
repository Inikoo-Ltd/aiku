<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from "vue";
import { faPresentation, faLink, faPaperclip } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getStyles } from "@/Composables/styles.js"
import { v4 as uuidv4 } from "uuid";
/* import PureInput from "@/Components/Pure/PureInput.vue"
import { ref } from "vue"
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue';
import InputUseOption from "@/Components/Pure/InputUseOption.vue" */


library.add(faPresentation, faLink, faPaperclip)

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
}>()

const screenWidth = ref(window.innerWidth);

// Fungsi untuk memperbarui ukuran layar saat berubah
const updateScreenWidth = () => {
    screenWidth.value = window.innerWidth;
};

onMounted(() => {
    window.addEventListener("resize", updateScreenWidth);
});

onUnmounted(() => {
    window.removeEventListener("resize", updateScreenWidth);
});

// Menentukan ukuran berdasarkan kondisi Mobile/Desktop
const iframeStyles = computed(() => {
    const baseStyles = getStyles(props.modelValue?.container?.properties) || {};

    if (props.modelValue?.link?.includes("wowsbar")) {
        if (screenWidth.value <= 768) {
            // Mobile (≤768px)
            return {
                ...baseStyles,
                width: "100%",
                height: "26vh", // Bisa diatur sesuai kebutuhan
            };
        }
    }

    return baseStyles;
});
</script>

<template>
    <div v-if="!modelValue.link || modelValue?.link == ''" type="button"
        class="relative block w-full p-12 text-center hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        <font-awesome-icon :icon="['fal', 'paperclip']" class="mx-auto h-12 w-12 text-gray-400" />
        <span class="mt-2 block text-sm font-semibold text-gray-900">I Frame</span>
    </div>

    <div v-else class="relative">
        <iframe :title="modelValue?.title || `iframe-${uuidv4()}`" :src="modelValue?.link" :style="iframeStyles" />
    </div>
</template>