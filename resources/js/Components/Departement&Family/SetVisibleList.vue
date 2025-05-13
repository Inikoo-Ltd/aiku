<script setup lang="ts">
import { routeType } from '@/types/route';
import { faImage, faEye, faEyeSlash, faExclamationTriangle } from '@far';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from "primevue/useconfirm";
import { router } from '@inertiajs/vue3';
import { notify } from '@kyvg/vue3-notification';
import EmptyState from '../Utils/EmptyState.vue';


const props = withDefaults(defineProps<{
    title: string,
    list_data: Array<any>,
    updateRoute: routeType,
    disable?: boolean,
}>(), {
    disable: false,
})


const confirm = useConfirm("confirm-hide");

const confirmHideAndShow = (event: MouseEvent, item: { id: number; name: string; show: boolean }) => {
    confirm.require({
        target: event.currentTarget, 
        group: "confirm-hide",
        message: item.show
            ? `Are you sure you want to hide "${item.name}" from view Families?`
            : `Are you sure you want to show "${item.name}" in view Families?`,
        header: 'Confirm Action',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: item.show ? 'Yes, hide it' : 'Yes, show it',
            severity: 'danger',
        },
        accept: () => SaveShowAndHide(item),
    });
};



const SaveShowAndHide = (item) => {
    router.patch(
        route(props.updateRoute.name, { ...props.updateRoute.parameters, masterProductCategory: item.id }),
        {
            show_in_website: !item.show_in_website
        },
        {
            preserveScroll: true,
            onStart: () => {
                item.loading = true;
            },
            onSuccess: (e) => {
                console.log(e)
                item.show_in_website = !item.show_in_website
            },
            onError: (errors) => {
                notify({
                    title: "Failed to Update",
                    text: errors,
                    type: "error"
                })
            },
            onFinish: () => {
                item.loading = false;
            },
        }
    );
}

</script>

<template>
    <div>
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 ">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-semibold">{{ title }}</h3>
                <!-- <Button label="Preview" :size="'xs'" :type="'tertiary'" :icon="faEye"
                @click="isModalFamiliesPreview = true" /> -->
            </div>

            <ul v-if="list_data?.length > 0" class="divide-y divide-gray-100 max-h-[calc(100vh-30vh)] min-h-12 overflow-auto">
                <li v-for="(item, index) in list_data" :key="item.slug"
                    class="flex items-center justify-between py-4 hover:bg-gray-50 px-2 rounded-lg transition">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-100 flex items-center justify-center rounded-lg overflow-hidden">
                            <img v-if="item.image" :src="item.image" alt="Item Image"
                                class="w-full h-full object-cover" />
                            <FontAwesomeIcon v-else :icon="faImage" class="text-gray-400 text-xl" />
                        </div>
                        <div>
                            <div class="font-medium text-gray-800">{{ item.name }}</div>
                            <div class="text-sm text-gray-500">{{ item.code }}</div>
                        </div>
                    </div>

                    <div class="text-gray-500 hover:text-primary cursor-pointer transition" title="Toggle visibility"
                        v-tooltip="'halooo'" @click="(e) => confirmHideAndShow(e, item)">
                        <FontAwesomeIcon :icon="item.show_in_website ? faEye : faEyeSlash" />
                    </div>
                </li>
            </ul>

            <EmptyState v-else />
        </div>
    </div>

    <ConfirmPopup group="confirm-hide">
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmPopup>
</template>
