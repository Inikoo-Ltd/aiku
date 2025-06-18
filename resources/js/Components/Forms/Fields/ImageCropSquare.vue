<script setup lang="ts">
import { ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExclamationCircle, faCheckCircle } from "@fas";
import { faUndoAlt, faInfoCircle } from "@fal";
import { faSpinnerThird } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";

import Dialog from "primevue/dialog";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { Cropper } from "vue-advanced-cropper";
import "vue-advanced-cropper/dist/style.css";

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faUndoAlt, faInfoCircle);

const props = defineProps<{
    form: Record<string, any>,
    fieldName: string,
    fieldData: {
        options: {
            aspectRatio?: { width: number, height: number },
            minAspectRatio?: { width: number, height: number },
            maxAspectRatio?: { width: number, height: number }
        }
    }
}>();

const numbKey = ref(0);
const tempImgToCrop = ref<string | null>(null);
const imgAfterCrop = ref<{ original: string } | null>(
    props.form[props.fieldName] ? props.form[props.fieldName] : null
);

const isOpenModalCrop = ref(false);
const _cropper = ref<InstanceType<typeof Cropper> | null>(null);

const onPickFile = async (file: File) => {
    if (!file) return;
    _cropper.value?.reset();
    isOpenModalCrop.value = true;
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (e) => {
        tempImgToCrop.value = e.target?.result as string;
    };
};

const dataURLtoBlob = (dataUrl: string): Blob => {
    const arr = dataUrl.split(",");
    const mime = arr[0].match(/:(.*?);/)![1];
    const bStr = atob(arr[1]);
    let n = bStr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bStr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
};

const submitCrop = async () => {
    props.form.errors[props.fieldName] = null;
    const result = await _cropper.value?.getResult();
    if (!result || !result.canvas) return;

    const imageDataURL = result.canvas.toDataURL();
    imgAfterCrop.value = { original: imageDataURL };

    const imageBlob = dataURLtoBlob(imageDataURL);
    props.form[props.fieldName] = new File([imageBlob], "avatar.png", { type: "image/png" });

    isOpenModalCrop.value = false;
};

const stencilProps = props.fieldData?.options?.minAspectRatio && props.fieldData?.options?.maxAspectRatio
    ? {
        minAspectRatio: props.fieldData.options.minAspectRatio,
        maxAspectRatio: props.fieldData.options.maxAspectRatio
    }
    : {
        aspectRatio: props.fieldData?.options?.aspectRatio
            ? props.fieldData.options.aspectRatio.width / props.fieldData.options.aspectRatio.height
            : 1
    };

watch(isOpenModalCrop, (val) => {
    if (val) _cropper.value?.refresh();
});
</script>

<template>
    <div class="w-fit min-w-32 relative">
        <!-- PrimeVue Dialog -->
        <Dialog v-model:visible="isOpenModalCrop" modal header="Crop Image" :style="{ width: '600px' }">
            <div class="w-full h-[300px] relative bg-gray-700">
                <Cropper
                    :key="numbKey"
                    ref="_cropper"
                    class="w-full h-full"
                    :src="tempImgToCrop"
                    :stencil-props="stencilProps"
                    imageClass="w-full h-full"
                    :auto-zoom="true"
                />
                <div @click="() => numbKey++"
                     class="select-none px-2 py-1 cursor-pointer absolute top-2 right-2 text-white border border-gray-300 hover:bg-white/80 hover:text-gray-700 rounded">
                    <FontAwesomeIcon :icon="['fal', 'undo-alt']" fixed-width />
                    {{ trans("Refresh") }}
                </div>
            </div>

            <div class="text-gray-500 italic text-xs mt-2">
                <FontAwesomeIcon :icon="['fal', 'info-circle']" fixed-width class="mr-1" />
                {{ trans("Use mouse scroll to zoom in and zoom out") }}
            </div>

            <div class="w-full mt-4">
                <Button @click="submitCrop" :label="trans('Crop')" full size="xl" />
            </div>
        </Dialog>

        <!-- Image Preview -->
        <div
            class="relative overflow-hidden h-40 min-w-32 aspect-square rounded-lg ring-1 ring-gray-500 shadow bg-gray-100"
            :class="form.errors[fieldName] ? 'errorShake' : ''"
        >
            <img
                v-if="imgAfterCrop?.original"
                :src="imgAfterCrop.original"
                alt="Preview"
                class="h-full w-full object-cover rounded"
            />
            <div v-else class="h-full w-full flex items-center justify-center text-gray-400 text-sm">
                {{ trans("No Image") }}
            </div>

            <label
                for="input-avatar-large"
                class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 text-sm font-medium text-white opacity-0 hover:opacity-100 transition-opacity duration-200 cursor-pointer"
            >
                <span>{{ trans("Change") }}</span>
                <input
                    id="input-avatar-large"
                    type="file"
                    accept="image/*"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    @change="onPickFile($event.target.files[0])"
                />
            </label>
        </div>

        <!-- Status Icon -->
        <div class="absolute top-2 right-0 pr-3 flex items-center pointer-events-none">
            <FontAwesomeIcon
                v-if="form.errors[fieldName]"
                :icon="['fas', 'exclamation-circle']"
                class="h-5 w-5 text-red-500"
            />
            <FontAwesomeIcon
                v-else-if="form.recentlySuccessful"
                :icon="['fas', 'check-circle']"
                class="h-5 w-5 text-green-500"
            />
        </div>

        <!-- Error Text -->
        <p v-if="form.errors[fieldName]" class="text-red-700 text-sm mt-1">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
