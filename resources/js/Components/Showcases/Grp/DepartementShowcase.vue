<script setup lang="ts">
import { ref,provide } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@far";
import { faInfoCircle } from "@fas";
import { faAlbumCollection } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { router, Link } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import { notify } from "@kyvg/vue3-notification";

import Image from "@/Components/Image.vue";
import Message from "primevue/message";
import CollectionList from "@/Components/Departement&Family/CollectionList.vue";

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        department: {
            data: {
                name: string;
                description: string;
                image: Array<string>;
                url_master: any;
            };
        };
        routeList: {
            collectionRoute: any;
            collections_route: any;
        };
        routes: {
            attach_collections_route: any;
            detach_collections_route: any;
        };
        collections: {
            data: Array<{
                id: number;
                name: string;
                description: string;
                image: Array<string>;
            }>;
        };
    };
}>();

const isLoadingSubmit = ref(false);
const unassignLoadingIds = ref<number[]>([]);
const isModalOpen = ref(false);
provide('isModalOpen', isModalOpen); // 🔁 Key harus sama persis dengan yang di-inject

const assignCollection = async (collections: any[]) => {
    const method = props.data.routes.attach_collections_route.method;
    const url = route(
        props.data.routes.attach_collections_route.name,
        props.data.routes.attach_collections_route.parameters
    );
    const collectionIds = collections.map((c) => c.id);

    router[method](
        url,
        { collections: collectionIds },
        {
            onBefore: () => (isLoadingSubmit.value = true),
            onError: (error) => {
                notify({
                    title: trans("Something went wrong."),
                    text: error?.products || trans("Failed to add collection."),
                    type: "error",
                });
            },
            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Successfully added portfolios"),
                    type: "success",
                });
                isModalOpen.value = false;
            },
            onFinish: () => {
                isLoadingSubmit.value = false;
            },
        }
    );
};
console.log('DepartementShowcase', props.data);
const UnassignCollection = async ( id: number ) => {
    unassignLoadingIds.value.push(id);
    const method = props.data.routes.detach_collections_route.method;
    const url = route(
        props.data.routes.detach_collections_route.name,
        {
            ...props.data.routes.detach_collections_route.parameters,
            collection: id,
        }
    );

    router[method](
        url,
        {
            onError: (error) => {
                notify({
                    title: trans("Something went wrong."),
                    text: error?.products || trans("Failed to remove collection."),
                    type: "error",
                });
            },
            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Collection has been removed."),
                    type: "success",
                });
            },
            onFinish: () => {
                unassignLoadingIds.value = unassignLoadingIds.value.filter(
                    (id) => id !== id
                );
            },
        }
    );
};
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <!-- Master Message -->
        <Message v-if="data.department?.url_master" severity="success" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">
                {{ trans("Right now you follow") }}
                <Link :href="route(data.department.url_master.name, data.department.url_master.parameters)"
                    class="underline font-bold">
                {{ trans("the master data") }}
                </Link>
            </span>
        </Message>

        <div class="grid grid-cols-8 gap-4 mt-4">
            <!-- Sidebar -->
            <div class="col-span-2">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
                        <Image v-if="data?.department?.image" :src="data?.department?.image" imageCover
                            class="w-full h-40 object-cover rounded-t-lg" />
                        <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                            <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                        </div>
                    </div>

                    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
                        <div class="font-medium">{{ data?.department?.name || "No label" }}</div>
                        <div class="text-gray-400">
                            {{ data?.department?.description || "No description" }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Collection List -->
            <div class="col-span-2">
                <CollectionList :collections="props.data.collections.data"
                    :routeFetch="props.data.routeList.collections_route" :canAdd="true" :loadingUnassignIds="unassignLoadingIds"
                    :isSubmitting="isLoadingSubmit" @assign="assignCollection" @unassign="UnassignCollection" />

            </div>
        </div>
    </div>

</template>
