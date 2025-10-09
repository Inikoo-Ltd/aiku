<script setup lang="ts">
import draggable from "vuedraggable"
import Drawer from 'primevue/drawer';
import { ref, computed } from "vue";
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
import { trans } from "laravel-vue-i18n"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import { set } from "lodash-es";

const props = defineProps<{
    data: {
        data: {
            component: string,
            fieldValue: {
                navigation: Array<any>
                navigation_bottom: Array<any>
            }
        }
    }
    autosaveRoute: routeType
}>()
console.log('-- Menu list props.data', props.data)

const emits = defineEmits<{
    (e: 'auto-save'): void
}>()

const confirm = useConfirm()
const visibleDrawer = ref(false);
const selectedMenu = ref(0)
const selectedArea = ref<'top' | 'bottom'>('top')
const deleteButtonRefs = ref<HTMLElement[]>([]);

// Initialize arrays if they don't exist
if (!props.data && !props.data?.data?.fieldValue?.navigation) {
    props.data.data.fieldValue.navigation = [];
}
if (!props.data && !props.data?.data?.fieldValue?.navigation_bottom) {
    props.data.data.fieldValue.navigation_bottom = [];
}

// Computed for merge the menu
// const allNavigation = computed(() => {
//     const topNav = props.data.data.fieldValue.navigation;
//     const bottomNav = props.data.data.fieldValue.navigation_bottom;
//     return [
//         ...topNav.map(item => ({ ...item, area: 'top' })),
//         ...bottomNav.map(item => ({ ...item, area: 'bottom' }))
//     ];
// });

const allowMove = (evt: any) => {
    // Allow move if drag handle is used OR if we're moving to empty area
    const isDragHandle = evt.originalEvent?.target?.closest('.drag-handle') !== null;
    const isEmptyTarget = evt.to && evt.to.children.length === 0;

    return isDragHandle || isEmptyTarget;
}

const SetMenuActive = (item: any, isTop: boolean) => {
    const area = isTop ? 'top' : 'bottom';
    const targetArray = isTop
        ? props.data.data.fieldValue.navigation
        : props.data.data.fieldValue.navigation_bottom;
    const index = targetArray.findIndex(nav => nav.id === item.id);

    if (index !== -1) {
        visibleDrawer.value = true
        selectedMenu.value = index
        selectedArea.value = area
    }
}

const onChangeNavigation = (setData: object) => {
    if (selectedArea.value === 'top') {
        if (props.data.data.fieldValue.navigation[selectedMenu.value]) {
            props.data.data.fieldValue.navigation[selectedMenu.value] = setData
        }
    } else {
        if (props.data.data.fieldValue.navigation_bottom[selectedMenu.value]) {
            props.data.data.fieldValue.navigation_bottom[selectedMenu.value] = setData
        }
    }
    debouncedSendUpdate()
}

const debouncedSendUpdate = debounce(() => autoSave(), 1000, {
    leading: false,
    trailing: true,
})

const addNavigation = (area: 'top' | 'bottom' = 'top') => {
    const newNav = {
        label: "New Navigation",
        id: ulid(),
        type: "single",
    };

    if (area === 'top') {
        if (!props.data.data) set(props.data, 'data', {});
        if (!props.data.data.fieldValue) set(props.data.data, 'fieldValue', {});
        if (!props.data.data.fieldValue.navigation) set(props.data.data.fieldValue, 'navigation', []);
        props.data.data.fieldValue.navigation.push(newNav);
    } else {
        if (!props.data.data) set(props.data, 'data', {});
        if (!props.data.data.fieldValue) set(props.data.data, 'fieldValue', {});
        if (!props.data.data.fieldValue.navigation_bottom) set(props.data.data.fieldValue, 'navigation_bottom', []);
        props.data.data.fieldValue.navigation_bottom.push(newNav);
    }

    debouncedSendUpdate()
}

const deleteNavigation = (item: any, area: 'top' | 'bottom') => {
    if (area === 'top') {
        const index = props.data.data.fieldValue.navigation.findIndex(nav => nav.id === item.id);
        if (index !== -1) {
            props.data.data.fieldValue.navigation.splice(index, 1);
        }
    } else {
        const index = props.data.data.fieldValue.navigation_bottom.findIndex(nav => nav.id === item.id);
        if (index !== -1) {
            props.data.data.fieldValue.navigation_bottom.splice(index, 1);
        }
    }
    debouncedSendUpdate()
}

// Handle drag end untuk area yang berbeda
const handleDragEnd = () => {
    autoSave();
}

// Get current item for drawer
const getCurrentItem = computed(() => {
    if (selectedArea.value === 'top') {
        return props?.data?.data?.fieldValue?.navigation[selectedMenu.value];
    } else {
        return props?.data?.data?.fieldValue?.navigation_bottom[selectedMenu.value];
    }
});

const autoSave = async (event?) => {
    emits('auto-save')
    console.log('Navigation data:', props.data.data.fieldValue.navigation);
    console.log('Navigation bottom data:', props.data.data.fieldValue.navigation_bottom);
}

</script>

<template>
    <!-- Top Navigation Area -->
    <div class="mb-4">
        <div class="text-sm font-medium text-gray-600 mb-2 px-2">Top Custom Menu</div>
        <div class="mb-3">
            <Button :label="'Add Top Navigation'" type="create" :size="'xs'" @click="() => addNavigation('top')" />
        </div>
        <draggable :list="data?.data?.fieldValue?.navigation" ghost-class="ghost" chosen-class="chosen"
            drag-class="dragging" group="navigation" itemKey="id"
            class="space-y-2 min-h-[100px] p-2 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50"
            :fallbackOnBody="true" :emptyInsertThreshold="100" @end="handleDragEnd">
            <template #item="{ element, index }">
                <div @click="() => SetMenuActive(element, true)"
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
                        <div class="text-xs text-gray-500">{{ element.type }}</div>
                    </div>

                    <!-- Delete Button -->
                    <button @click.stop="() => deleteNavigation(element, 'top')"
                        class="opacity-0 group-hover:opacity-100 px-3 py-2 text-red-500 hover:text-red-700 transition duration-150"
                        title="Delete menu">
                        <FontAwesomeIcon :icon="faTrash" />
                    </button>
                </div>
            </template>
            <template #fallback>
                <div class="text-center text-gray-400 py-8 border-2 border-dashed border-gray-300 rounded-lg">
                    <div class="text-sm">Drop top navigation items here</div>
                    <div class="text-xs mt-1">or click "Add Top Navigation" to create new item</div>
                </div>
            </template>
        </draggable>
    </div>

    <!-- Static Menu Separator -->
    <div class="flex items-center my-6">
        <!-- <div class="flex-grow border-t border-gray-300"></div> -->
        <div class="px-8 py-5 bg-gray-200 w-full text-center text-gray-600 font-medium text-sm border border-gray-300 rounded">
            {{ trans("Area reserved by system") }}
            <InformationIcon :information="trans('Automatically showed Departments list')" />
        </div>
        <!-- <div class="flex-grow border-t border-gray-300"></div> -->
    </div>

    <!-- Bottom Navigation Area -->
    <div class="mb-4">
        <div class="text-sm font-medium text-gray-600 mb-2 px-2">Bottom Custom Menu</div>
        <div class="mb-3">
            <Button :label="'Add Bottom Navigation'" type="create" :size="'xs'"
                @click="() => addNavigation('bottom')" />
        </div>
        <draggable :list="data?.data?.fieldValue?.navigation_bottom" ghost-class="ghost" chosen-class="chosen"
            drag-class="dragging" group="navigation" itemKey="id"
            class="space-y-2 min-h-[100px] p-2 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50"
            :fallbackOnBody="true" :emptyInsertThreshold="100" @end="handleDragEnd">
            <template #item="{ element, index }">
                <div @click="() => SetMenuActive(element, false)"
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
                        <div class="text-xs text-gray-500">{{ element.type }}</div>
                    </div>

                    <!-- Delete Button -->
                    <button @click.stop="() => deleteNavigation(element, 'bottom')"
                        class="opacity-0 group-hover:opacity-100 px-3 py-2 text-red-500 hover:text-red-700 transition duration-150"
                        title="Delete menu">
                        <FontAwesomeIcon :icon="faTrash" />
                    </button>
                </div>
            </template>
            <template #fallback>
                <div class="text-center text-gray-400 py-8 border-2 border-dashed border-gray-300 rounded-lg">
                    <div class="text-sm">Drop bottom navigation items here</div>
                    <div class="text-xs mt-1">or click "Add Bottom Navigation" to create new item</div>
                </div>
            </template>
        </draggable>
    </div>

    <!-- Drawer for Menu Editing -->
    <Drawer v-model:visible="visibleDrawer" :header="getCurrentItem?.label || 'Edit Menu'" position="right"
        :pt="{ root: { style: 'width: 40vw' } }">
        <EditMode v-if="getCurrentItem" v-model="getCurrentItem"
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