<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Cleaned & Optimized by ChatGPT
-->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { ref, computed, inject } from "vue";
import Drawer from "primevue/drawer";
import Image from "primevue/image"; // ✅ added
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";
import { Textarea } from "primevue";
import axios from "axios";
import SideEditorInputHTML from "@/Components/CMS/Fields/SideEditorInputHTML.vue";

import { faChevronUp, faChevronDown, faExpand, faMinimize, faXmark } from "@fortawesome/free-solid-svg-icons";
import { faCamera } from "@fortawesome/free-solid-svg-icons"; // ✅ camera here
import { faExclamationCircle } from "@fortawesome/free-solid-svg-icons";
import { ulid } from "ulid";
import { notify } from "@kyvg/vue3-notification";
import { cloneDeep } from "lodash";
import TableSetFamilyInMasterCreate from "./TableSetFamilyInMasterCreate.vue";

const props = defineProps<{
    storeProductRoute: routeType
    showDialog: boolean,
    shopsData?: any
}>();

const emits = defineEmits(["update:showDialog"]);
const tableData = ref(cloneDeep(props.shopsData));
const detailsVisible = ref(true);
const tableVisible = ref(true);
const isFull = ref(false);
const key = ref(ulid());
const layout = inject('layout', {});

// Inertia form
const form = useForm({
    code: "",
    name: "",
    description: "",
    description_title: "",
    description_extra: "",
    price: 0,
    shop_family: null,
    image: null
});

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const chooseImage = () => {
    fileInput.value?.click();
};

const previewImage = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
        form.image = file;
    }
};

const resetImage = () => {
    previewUrl.value = null;
    form.image = null;
    if (fileInput.value) fileInput.value.value = "";
};

const submitForm = async (redirect = true) => {
    form.processing = true
    form.errors = {}

    const finalDataTable: Record<number, { price: number | string }> = {}
    for (const tableDataItem of tableData.value.data) {
        finalDataTable[tableDataItem.id] = {
            create_webpage: tableDataItem.create_webpage,
        }
    }

    // Build payload manual
    const payload: any = {
        ...form.data(),
        shop_family: finalDataTable
    }

    // Hapus image kalau tidak diganti user
    if (!(form.image instanceof File)) {
        delete payload.image
    }

    try {
        const response = await axios.post(
            route(props.storeProductRoute.name, props.storeProductRoute.parameters),
            payload,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        if (redirect) {
            // If you need redirect, you can call router.visit() here
            router.visit(route('grp.masters.master_shops.show.master_families.show', {
                masterShop: route().params['masterShop'],
                masterFamily: response.data.slug
  
            }))
        } else {
            tableData.value.data = cloneDeep(props.shopsData.data);
            form.reset()
            key.value = ulid()
            notify({
                title: trans("Success!"),
                text: trans("Master family has been created"),
                type: "success"
            })

        }
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            form.errors = error.response.data.errors || {}
            if (form.errors.code || form.errors.unit || form.errors.name) {
                detailsVisible.value = true
            }
        } else {
            notify({
                title: trans("Something went wrong"),
                text: error.message || trans("Please try again"),
                type: 'error'
            })
        }
    } finally {
        form.processing = false
    }
}

const drawerVisible = computed({
    get: () => props.showDialog,
    set: (val: boolean) => emits("update:showDialog", val),
});

const toggleFull = () => {
    isFull.value = !isFull.value;
};
</script>

<template>
    <Drawer v-model:visible="drawerVisible" position="right" :class="[isFull ? '!w-full' : '!w-full md:!w-1/2']">
        <!-- Header -->
        <template #header>
            <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2 flex-1">
                {{ trans("Create Master Family") }}
            </h2>
            <!-- Tombol Toggle Fullscreen -->
            <button @click="toggleFull" class="text-gray-500 hover:text-gray-700 mx-3">
                <FontAwesomeIcon :icon="isFull ? faMinimize : faExpand" />
            </button>
        </template>

        <!-- Content -->
        <div class="p-4 pt-0 space-y-6 overflow-y-auto">

            <!-- Family Detail -->
            <div class="pt-4">
                <!-- Toggle -->
                <button
                    class="w-full flex items-center justify-between border-b pb-2 text-lg font-semibold text-gray-600 hover:text-gray-800"
                    @click="detailsVisible = !detailsVisible">
                    <span>
                        {{ trans("Family Detail") }}
                    </span>
                    <FontAwesomeIcon :icon="detailsVisible ? faChevronUp : faChevronDown" class="text-xs" />
                </button>

                <!-- Details Form -->
                <div v-if="detailsVisible" class="mt-4">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- Kolom kiri: Gambar -->
                        <div class="flex-shrink-0 flex justify-center md:justify-start">
                            <div class="relative w-32 h-32 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center bg-gray-50 hover:bg-gray-100 cursor-pointer transition shadow-sm"
                                @click="(e) => chooseImage(e)">
                                <!-- Gambar baru (upload preview) -->
                                <img v-if="previewUrl" :src="previewUrl" alt="Preview"
                                    class="w-full h-full object-cover rounded-xl" />

                                <!-- Gambar lama dari DB -->
                                <Image v-else-if="form.image" :src="form.image" alt="Existing"
                                    class="w-full h-full object-cover rounded-xl" />

                                <!-- Kalau kosong -->
                                <div v-else class="flex flex-col items-center text-gray-400 text-xs">
                                    <FontAwesomeIcon :icon="faCamera" class="text-lg mb-1" />
                                    <span>Upload</span>
                                </div>

                                <!-- Tombol remove -->
                                <button v-if="previewUrl || form.image" @click.stop="resetImage"
                                    class="absolute top-1 right-1 bg-white text-gray-500 rounded-full p-1 shadow hover:text-red-500">
                                    <FontAwesomeIcon :icon="faXmark" class="w-3 h-3" />
                                </button>
                            </div>
                            <input type="file" accept="image/*" ref="fileInput" class="hidden" @change="previewImage" />
                        </div>

                        <!-- Kolom kanan: Form -->
                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Code -->
                            <div>
                                <label class="font-medium block mb-1 text-sm">Code</label>
                                <PureInput type="text" v-model="form.code" @update:model-value="form.errors.code = null"
                                    class="w-full" />
                                <small v-if="form.errors.code"
                                    class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                    <FontAwesomeIcon :icon="faExclamationCircle" />
                                    {{ form.errors.code.join(", ") }}
                                </small>
                            </div>

                            <!-- Name -->
                            <div>
                                <label class="font-medium block mb-1 text-sm">Name</label>
                                <PureInput type="text" v-model="form.name" @update:model-value="form.errors.name = null"
                                    class="w-full" />
                                <small v-if="form.errors.name"
                                    class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                    <FontAwesomeIcon :icon="faExclamationCircle" />
                                    {{ form.errors.name.join(", ") }}
                                </small>
                            </div>

                            <!-- Description: Title -->
                            <div class="md:col-span-2">
                                <label class="font-medium block mb-1 text-sm">Description Title</label>
                                <PureInput v-model="form.description_title"
                                    @update:model-value="form.errors.description_title = null" class="w-full" />
                                <small v-if="form.errors.description_title"
                                    class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                    <FontAwesomeIcon :icon="faExclamationCircle" />
                                    {{ form.errors.description_title.join(", ") }}
                                </small>
                            </div>

                            <!-- Description -->
                            <div class="md:col-span-2">
                                <label class="font-medium block mb-1 text-sm">Description</label>
                                <SideEditorInputHTML rows="3" v-model="form.description" :key="key"
                                    @update:model-value="form.errors.description = null" class="w-full" />
                                <small v-if="form.errors.description"
                                    class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                    <FontAwesomeIcon :icon="faExclamationCircle" />
                                    {{ form.errors.description.join(", ") }}
                                </small>
                            </div>

                            <!-- Description Extra -->
                            <div class="md:col-span-2">
                                <label class="font-medium block mb-1 text-sm">Description Extra</label>
                                <SideEditorInputHTML rows="3" v-model="form.description_extra" :key="key"
                                    @update:model-value="form.errors.description_extra = null" class="w-full" />
                                <small v-if="form.errors.description_extra"
                                    class="text-red-500 text-xs flex items-center gap-1 mt-1">
                                    <FontAwesomeIcon :icon="faExclamationCircle" />
                                    {{ form.errors.description_extra.join(", ") }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Table Product Section -->
            <div class="">
                <!-- Toggle -->
                <button
                    class="w-full flex items-center justify-between border-b pb-2 text-lg font-semibold text-gray-600 hover:text-gray-800"
                    @click="tableVisible = !tableVisible">
                    <span>
                        {{ trans("Shop Family") }}
                    </span>
                    <FontAwesomeIcon :icon="tableVisible ? faChevronUp : faChevronDown" class="text-xs" />
                </button>

                <!-- Table -->
                <div v-if="tableVisible" class="mt-4">
                    <TableSetFamilyInMasterCreate v-model="tableData" :master_price="form.price" :key="key" />
                    <small v-if="form.errors.shop_family" class="text-red-500 flex items-center gap-1">
                        {{ form.errors.shop_family.join(", ") }}
                    </small>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="flex justify-end gap-3 border-t pt-3">
                <Button :label="trans('Cancel')" type="negative" class="!px-5"
                    @click="emits('update:showDialog', false)" />
                <Button type="secondary" :loading="form.processing" class="!px-6" icon="fas fa-plus"
                    :label="'save & create another one'" @click="submitForm(false)" />

                <Button type="save" :loading="form.processing" class="!px-6" @click="submitForm" />
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}
</style>
