<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExclamationCircle, faCheckCircle, faTimes } from "@fas";
import { faUndoAlt, faInfoCircle, faImage, faTrashAlt, faPen, faCrop } from "@fal";
import { faSpinnerThird } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";

import Dialog from "primevue/dialog";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { Cropper } from "vue-advanced-cropper";
import "vue-advanced-cropper/dist/style.css";

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faUndoAlt, faInfoCircle, faTimes, faImage, faTrashAlt, faPen, faCrop)

const props = defineProps<{
    form: Record<string, any>,
    fieldName: string,
    fieldData: {
        required?: boolean,
        hasOther?: {
            name: string,
            label?: string,
            placeholder?: string,
            information?: string
        },
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

const altFieldName = props.fieldData?.hasOther?.name;
const inputId = `input-image-${props.fieldName}`;

const altText = computed({
    get: () => (altFieldName ? props.form[altFieldName] : null),
    set: (value: string | number | null) => {
        if (altFieldName) {
            props.form[altFieldName] = value;
            props.form.errors[altFieldName] = null;
        }
    }
});

if (imgAfterCrop.value && !(props.form[props.fieldName] instanceof File) && typeof props.form.defaults === "function") {
    props.form.defaults({ [props.fieldName]: null });
    props.form[props.fieldName] = null;
}

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


const deleteImage = () => {
    imgAfterCrop.value = null
    props.form[props.fieldName] = null
}


watch(isOpenModalCrop, (val) => {
    if (val) _cropper.value?.refresh();
});
</script>

<template>
    <div class="min-w-32" :class="altFieldName ? 'w-full' : 'w-fit'">
        <!-- PrimeVue Dialog -->
        <Dialog v-model:visible="isOpenModalCrop" modal :header="trans('Crop Image')" :style="{ width: '600px' }"
            :breakpoints="{ '640px': '95vw' }">
            <div class="w-full h-[320px] relative bg-gray-900 rounded-lg overflow-hidden ring-1 ring-gray-300">
                <Cropper :key="numbKey" ref="_cropper" class="w-full h-full" :src="tempImgToCrop"
                    :stencil-props="stencilProps" imageClass="w-full h-full" :auto-zoom="true" />
                <button type="button" @click="() => numbKey++" v-tooltip="trans('Reset crop')"
                    class="select-none px-2.5 py-1.5 text-xs absolute top-2 right-2 text-white bg-black/40 backdrop-blur-sm border border-white/30 hover:bg-white hover:text-gray-800 rounded-md transition-colors duration-150">
                    <FontAwesomeIcon :icon="['fal', 'undo-alt']" fixed-width aria-hidden="true" />
                    {{ trans("Refresh") }}
                </button>
            </div>

            <div class="text-gray-500 italic text-xs mt-2.5">
                <FontAwesomeIcon :icon="['fal', 'info-circle']" fixed-width class="mr-1" aria-hidden="true" />
                {{ trans("Use mouse scroll to zoom in and zoom out") }}
            </div>

            <div class="w-full mt-4">
                <Button @click="submitCrop" :label="trans('Crop')" :icon="['fal', 'crop']" full size="xl" />
            </div>
        </Dialog>

        <div class="flex flex-wrap items-start gap-x-5 gap-y-3">
            <!-- Image Preview -->
            <div class="group relative h-40 min-w-32 aspect-square rounded-xl overflow-hidden transition duration-200"
                :class="[
                    imgAfterCrop?.original
                        ? 'ring-1 ring-gray-300 shadow-sm bg-gray-50 hover:ring-gray-400 hover:shadow-md'
                        : 'border-2 border-dashed border-gray-300 bg-gray-50/70 hover:border-indigo-400 hover:bg-indigo-50/40',
                    form.errors[fieldName] ? 'errorShake ring-1 ring-red-400 border-red-400' : ''
                ]">
                <img v-if="imgAfterCrop?.original" :src="imgAfterCrop.original" :alt="altText || trans('Preview')"
                    class="h-full w-full object-cover" />

                <!-- Empty state -->
                <label v-else :for="inputId"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 cursor-pointer text-gray-400 group-hover:text-indigo-500 transition-colors duration-200">
                    <FontAwesomeIcon :icon="['fal', 'image']" class="text-2xl" aria-hidden="true" />
                    <span class="text-xs font-medium">{{ trans("Upload image") }}</span>
                </label>

                <!-- Hover Actions -->
                <div v-if="imgAfterCrop?.original"
                    class="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-200">
                    <label :for="inputId" v-tooltip="trans('Change image')"
                        class="flex items-center justify-center h-9 w-9 rounded-full bg-white/90 text-gray-700 hover:bg-white hover:text-indigo-600 shadow cursor-pointer transition-colors duration-150">
                        <FontAwesomeIcon :icon="['fal', 'pen']" class="text-sm" aria-hidden="true" />
                        <span class="sr-only">{{ trans("Change image") }}</span>
                    </label>

                    <button v-if="fieldData.required == false" @click="deleteImage" type="button"
                        v-tooltip="trans('Delete image')"
                        class="flex items-center justify-center h-9 w-9 rounded-full bg-white/90 text-gray-700 hover:bg-red-500 hover:text-white shadow transition-colors duration-150">
                        <FontAwesomeIcon :icon="['fal', 'trash-alt']" class="text-sm" aria-hidden="true" />
                        <span class="sr-only">{{ trans("Delete image") }}</span>
                    </button>
                </div>

                <input :id="inputId" type="file" accept="image/*" class="sr-only"
                    @change="onPickFile($event.target.files[0]); $event.target.value = ''" />
            </div>

            <!-- Alt Text -->
            <div v-if="altFieldName" class="flex-1 min-w-64 max-w-md">
                <label :for="`input-alt-${altFieldName}`" class="flex items-center gap-x-1 text-sm font-medium text-gray-500">
                    {{ fieldData.hasOther?.label ?? trans("Alt text") }}
                    <span v-if="fieldData.hasOther?.information" v-tooltip="fieldData.hasOther.information"
                        class="opacity-50 hover:opacity-100 cursor-pointer">
                        <FontAwesomeIcon :icon="['fal', 'info-circle']" class="text-gray-500" fixed-width aria-hidden="true" />
                    </span>
                </label>

                <div class="mt-1.5">
                    <PureInput
                        v-model="altText"
                        :inputName="`input-alt-${altFieldName}`"
                        :placeholder="fieldData.hasOther?.placeholder ?? trans('Describe the image')"
                        :maxLength="255"
                        :isError="!!form.errors[altFieldName]"
                        :class="form.errors[altFieldName] ? 'errorShake' : ''"
                    />
                </div>

                <p v-if="form.errors[altFieldName]" class="mt-1 text-sm text-red-600">
                    {{ form.errors[altFieldName] }}
                </p>
            </div>
        </div>

        <!-- Status -->
        <p v-if="form.errors[fieldName]" class="flex items-center gap-x-1.5 text-red-600 text-sm mt-2">
            <FontAwesomeIcon :icon="['fas', 'exclamation-circle']" class="h-4 w-4" aria-hidden="true" />
            {{ form.errors[fieldName] }}
        </p>
        <p v-else-if="form.recentlySuccessful" class="flex items-center gap-x-1.5 text-green-600 text-sm mt-2">
            <FontAwesomeIcon :icon="['fas', 'check-circle']" class="h-4 w-4" aria-hidden="true" />
            {{ trans("Saved") }}
        </p>
    </div>
</template>
