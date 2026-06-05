<script setup lang="ts">
import { computed, ref } from "vue";
import { trans } from "laravel-vue-i18n";
import axios from "axios";
import Draggable from "vuedraggable";

import Button from "@/Components/Elements/Buttons/Button.vue";
import InputText from "primevue/inputtext";
import Accordion from "primevue/accordion";
import AccordionPanel from "primevue/accordionpanel";
import AccordionHeader from "primevue/accordionheader";
import AccordionContent from "primevue/accordioncontent";
import ConfirmPopup from "primevue/confirmpopup";
import { useConfirm } from "primevue/useconfirm";

import Modal from "@/Components/Utils/Modal.vue";
import BlockList from "@/Components/CMS/Webpage/BlockList.vue";
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue";

import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import { getBlueprint } from "@/Composables/getBlueprintWorkshop";
import { faBars, faTrashAlt } from "@far";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

const props = defineProps<{
    uploadRoutes: routeType;
    modelValue: any[];
}>();

const emit = defineEmits<{
    (e: "update:modelValue", value: any[]): void;
}>();

const model = computed({
    get: () => props.modelValue ?? [],
    set: (value) => emit("update:modelValue", value),
});

const confirm = useConfirm();

const modelModalBlocklist = ref(false);
const webBlockTypes = ref<any>({});
const activeAccordion = ref<string[]>(["0"]);

const getWebBlockTypes = async () => {
    try {
        const { data } = await axios.get(
            route("grp.json.web-block-types.index")
        );

        webBlockTypes.value = {
            data: data.data,
        };

        modelModalBlocklist.value = true;
    } catch {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to load block types"),
            type: "error",
        });
    }
};

const onPickBlock = (block: any) => {
    if (model.value.length >= 4) {
        notify({
            title: trans("Limit reached"),
            text: trans("Maximum 4 blocks allowed"),
            type: "warn",
        });

        modelModalBlocklist.value = false;

        return;
    }

    modelModalBlocklist.value = false;

    model.value = [
        ...model.value,
        {
            id: crypto.randomUUID(),
            name:
                block.name ||
                block.title ||
                block.code ||
                `Block ${model.value.length + 1}`,
            data: {
                fieldValue: block.data?.fieldValue,
            },
            code: block.code,
        },
    ];

    activeAccordion.value = [String(model.value.length - 1)];
};

const removeBlock = (index: number) => {


    model.value = model.value.filter((_, i) => i !== index);
};

const updateBlockName = (index: number, name: string) => {
    const blocks = [...model.value];

    blocks[index] = {
        ...blocks[index],
        name,
    };

    model.value = blocks;
};

const updateBlockFieldValue = (index: number, fieldValue: any) => {
    const blocks = [...model.value];

    blocks[index] = {
        ...blocks[index],
        data: {
            ...blocks[index].data,
            fieldValue,
        },
    };

    model.value = blocks;
};

const updateBlocks = (blocks: any[]) => {
    model.value = blocks;
};
</script>

<template>
    <div class="space-y-4">
        <ConfirmPopup />

        <div class="flex items-center justify-end">
            <Button type="create" size="xs" v-if="model.length < 4" :label="trans('Add Tab')"
                @click="getWebBlockTypes" />
        </div>

        <div v-if="!model.length" class="border-2 border-dashed border-gray-300 rounded-xl p-12">
            <div class="flex flex-col items-center gap-4">
                <i class="pi pi-clone text-5xl text-gray-400" />

                <div class="text-center">
                    <div class="font-medium">
                        {{ trans("No model added") }}
                    </div>

                    <div class="text-sm text-gray-500 mt-1">
                        {{
                            trans(
                                "Start building your page by adding a tabs"
                            )
                        }}
                    </div>
                </div>

                <Button type="create" v-if="model.length < 4" :label="trans('Add First Tab')"
                    @click="getWebBlockTypes" />
            </div>
        </div>

        <Draggable v-else :model-value="model" item-key="id" handle=".drag-handle" animation="200"
            ghost-class="opacity-40" class="space-y-3" @update:model-value="updateBlocks">
            <template #item="{ element: block, index }">
                <div
                    class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
                    <Accordion v-model:value="activeAccordion" multiple>
                        <AccordionPanel :value="String(index)">
                            <AccordionHeader>
                                <div class="flex items-center w-full gap-2 py-1 pr-2" @click.stop>
                                    <div
                                        class="drag-handle flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-500 cursor-move">
                                        <FontAwesomeIcon :icon="faBars" />
                                    </div>

                                    <InputText :model-value="block.name" class="w-[80%]"
                                        :placeholder="trans('Tab Name')" @update:model-value="
                                            updateBlockName(index, $event)
                                            " />

                                    <button type="button"
                                        class="flex items-center justify-center w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 transition"
                                        @click.stop="removeBlock(index)">
                                        <FontAwesomeIcon :icon="faTrashAlt" />
                                    </button>
                                </div>
                            </AccordionHeader>

                            <AccordionContent>
                                <div class="border-t border-gray-100 p-4">
                                    <SideEditor v-if="block?.data?.fieldValue" :model-value="block.data.fieldValue
                                        " :blueprint="getBlueprint(block.code)
                                            " :uploadImageRoute="uploadRoutes
                                                " @update:model-value="
                                                updateBlockFieldValue(
                                                    index,
                                                    $event
                                                )
                                                " />
                                </div>
                            </AccordionContent>
                        </AccordionPanel>
                    </Accordion>
                </div>
            </template>
        </Draggable>

        <Modal :isOpen="modelModalBlocklist" @onClose="modelModalBlocklist = false">
            <BlockList :onPickBlock="onPickBlock" :webBlockTypes="webBlockTypes" scope="element" />
        </Modal>
    </div>
</template>

<style scoped></style>