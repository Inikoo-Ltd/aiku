<script setup lang="ts">
import draggable from "vuedraggable"
import Drawer from 'primevue/drawer';
import { ref } from "vue";
import EditMode from "../Website/Menus/EditMode/EditMode.vue";
import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import axios from "axios";
import { debounce } from "lodash-es"
import { ulid } from "ulid";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faTrash } from "@fas";
import { useConfirm } from "primevue/useconfirm"
import ConfirmPopup from "primevue/confirmpopup"
import { faExclamationTriangle } from "@far";
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    data: {
        data: {
            component: string,
            fieldValue: {
                navigation: Array<any>
            }
        }
    }
    autosaveRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'auto-save'): void
}>()


const confirm = useConfirm()
const visibleDrawer = ref(false);
const selectedMenu = ref(0)
const deleteButtonRefs = ref<HTMLElement[]>([]);

const allowMove = (evt: any) => {
    return evt.originalEvent?.target?.closest('.drag-handle') !== null
}

const SetMenuActive = (index: number) => {
    visibleDrawer.value = true
    selectedMenu.value = index
}

const onChangeNavigation = (setData: object) => {
    props.data.data.fieldValue.navigation[selectedMenu] = setData
    debouncedSendUpdate()
}

const debouncedSendUpdate = debounce(() => autoSave(), 1000, {
    leading: false,
    trailing: true,
})

const addNavigation = () => {
    props.data.data.fieldValue.navigation.push({
        label: "New Navigation",
        id: ulid(),
        type: "single",
    })
    debouncedSendUpdate()
}

const deleteNavigation = (index: Number) => {
    props.data.data.fieldValue.navigation.splice(index, 1)
    debouncedSendUpdate()
}


/* const confirmDelete = (index: number) => {
    const target = deleteButtonRefs.value[index];
    if (!target) return;

    confirm.require({
        target,
        message: "Are you sure you want to delete?",
        rejectProps: {
            label: "No",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Yes",
        },
        accept: () => {
            deleteNavigation(index);
        },
    });
}; */


const autoSave = async () => {
   emits('auto-save')
}

</script>

<template>
    <!-- Add Menu Button -->
    <div class="flex justify-end m-2">
        <Button :label="'add Navigation'" type="create" :size="'xs'" @click="() => addNavigation()" />
    </div>

    <!-- Menu List -->
    <draggable :list="data.data.fieldValue.navigation" ghost-class="ghost" chosen-class="chosen" drag-class="dragging"
        group="column" itemKey="id" class="space-y-2" :move="allowMove" :fallbackOnBody="true" @end="()=>autoSave()">
        <template #item="{ element, index }">
            <div @click="() => SetMenuActive(index)"
                class="group flex items-center bg-white border border-gray-200 rounded shadow-sm overflow-hidden transition-transform duration-200 cursor-pointer hover:ring-2 hover:ring-indigo-400">
                <!-- Drag Handle -->
                <div class="drag-handle cursor-move px-3 py-2 text-gray-500 hover:text-indigo-600"
                    title="Drag to reorder" @click.stop>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                    </svg>
                </div>

                <!-- Label -->
                <div class="flex-1 px-4 py-2">
                    <div class="text-sm font-semibold text-gray-700">{{ element.label }}</div>
                </div>

                <!-- Delete Button -->
                <!-- Add :ref to button -->
                <button :ref="el => deleteButtonRefs[index] = el" @click.stop="() => deleteNavigation(index)"
                    class="opacity-0 group-hover:opacity-100 px-3 py-2 text-red-500 hover:text-red-700 transition duration-150"
                    title="Delete menu">
                    <FontAwesomeIcon :icon="faTrash" />
                </button>


            </div>
        </template>

    </draggable>

    <!-- Drawer for Menu Editing -->
    <Drawer v-model:visible="visibleDrawer" :header="data.data.fieldValue.navigation[selectedMenu]?.label"
        position="right" :pt="{ root: { style: 'width: 40vw' } }">
        <EditMode v-model="data.data.fieldValue.navigation[selectedMenu]"
            @update:model-value="(data) => onChangeNavigation(data)" />
    </Drawer>

    <ConfirmPopup>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmPopup>
</template>


<style scoped lang="scss">
.ghost {
    opacity: 0.5;
    background-color: #e2e8f0;
    border: 2px dashed #4F46E5;
}

/* .chosen {
    opacity: 0;
}

.dragging {
    transform: scale(1.05);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    cursor: grabbing;
} */
</style>
