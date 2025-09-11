<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Cleaned & Optimized by ChatGPT
-->

<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { ref, computed, inject } from "vue";
import Drawer from "primevue/drawer";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";
import {Textarea } from "primevue";
import axios from "axios";
import SideEditorInputHTML from "@/Components/CMS/Fields/SideEditorInputHTML.vue";


import { faChevronUp, faChevronDown } from "@far";
import { ulid } from "ulid";
import { notify } from "@kyvg/vue3-notification";
import { cloneDeep } from "lodash";
import { faMinus, faPlus, faTags, faExpand } from "@fal"
import { faExclamationCircle } from "@fas"
import { faArrowTrendDown, faArrowTrendUp, faMinimize } from "@fortawesome/free-solid-svg-icons"
import TableSetFamilyInMasterCreate from "./TableSetFamilyInMasterCreate.vue"


const props = defineProps<{
    storeProductRoute: routeType
    showDialog: boolean,
    shopsData?: any
}>();

const emits = defineEmits(["update:showDialog"]);
const tableData = ref(cloneDeep(props.shopsData));
const detailsVisible = ref(true);
const tableVisible = ref(true);
const isFull = ref(false); // <-- state untuk toggle full screen
const key = ref(ulid())
const layout = inject('layout', {});

// Inertia form
const form = useForm({
    code: "",
    name: "",
    description: "",
    description_title: "",
    description_extra: "",
    price: 0,
    shop_family: null
})



const submitForm = async (redirect = true) => {
    form.processing = true
    form.errors = {}

    const finalDataTable: Record<number, { price: number | string }> = {}
    for (const tableDataItem of tableData.value.data) {
        finalDataTable[tableDataItem.id] = {
            create_webpage: tableDataItem.create_webpage || false
        }
    }

    form.shop_family = finalDataTable


    try {
        const response = await axios.post(
            route(props.storeProductRoute.name, props.storeProductRoute.parameters),
            form.data(), // <-- same as inertia form payload
        )

        console.log("pppp", response.data, route().params)

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
            // Laravel validation errors → hydrate into inertia form
            form.errors = error.response.data.errors || {}
            if (form.errors.code || form.errors.description || form.errors.name) {
                detailsVisible.value = true
            }
        } else {
            console.error("Unexpected error:", error)
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

console.log(props)
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
                <div v-if="detailsVisible"
                    class="grid grid-cols-2 gap-6 mt-4">
                    <!-- Code -->
                    <div>
                        <label class="font-medium block mb-1 text-sm">Code</label>
                        <PureInput type="text" v-model="form.code" @update:model-value="form.errors.code = null"
                            class="w-full" />
                        <small v-if="form.errors.code" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faExclamationCircle" />
                            {{ form.errors.code.join(", ") }}
                        </small>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="font-medium block mb-1 text-sm">Name</label>
                        <PureInput type="text" v-model="form.name" @update:model-value="form.errors.name = null"
                            class="w-full" />
                        <small v-if="form.errors.name" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faExclamationCircle" />
                            {{ form.errors.name.join(", ") }}
                        </small>
                    </div>

                    <!-- Description: Title -->
                    <div class="col-span-2">
                        <label class="font-medium block mb-1 text-sm">Description Title</label>
                        <PureInput  v-model="form.description_title" @update:model-value="form.errors.description_title = null"
                            class="w-full" />
                        <small v-if="form.errors.description_title" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faExclamationCircle" />
                            {{ form.errors.description_title.join(", ") }}
                        </small>
                    </div>

                    <!-- Description -->
                    <div class="col-span-2">
                        <label class="font-medium block mb-1 text-sm">Description</label>
                        <SideEditorInputHTML rows="3" v-model="form.description" @update:model-value="form.errors.description = null"
                            class="w-full" />
                        <small v-if="form.errors.description" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faExclamationCircle" />
                            {{ form.errors.description.join(", ") }}
                        </small>
                    </div>

                    <!-- Description: Extra -->
                    <div class="col-span-2">
                        <label class="font-medium block mb-1 text-sm">Description Extra</label>
                        <SideEditorInputHTML rows="3" v-model="form.description_extra" @update:model-value="form.errors.description_extra = null"
                            class="w-full" />
                        <small v-if="form.errors.description_extra" class="text-red-500 text-xs flex items-center gap-1 mt-1">
                            <FontAwesomeIcon :icon="faExclamationCircle" />
                            {{ form.errors.description_extra.join(", ") }}
                        </small>
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
                <Button
                    :label="trans('Cancel')"
                    type="negative"
                    class="!px-5"
                    @click="emits('update:showDialog', false)"
                />
                <Button
                    type="secondary"
                    :loading="form.processing"
                    class="!px-6"
                    icon="fas fa-plus"
                    :label="'save & create another one'"
                    @click="submitForm(false)"
                />

                <Button
                    type="save"
                    :loading="form.processing"
                    class="!px-6"
                    @click="submitForm"
                />
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
