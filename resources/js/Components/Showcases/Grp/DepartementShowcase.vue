<script setup lang="ts">
import { ref } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@far";
import { faInfoCircle } from "@fas";
import { faAlbumCollection, faUnlink } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { router, Link } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import { notify } from "@kyvg/vue3-notification";

import Image from "@/Components/Image.vue";
import Message from "primevue/message";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";
import CollectionSelector from "@/Components/Departement&Family/CollectionSelector.vue";

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

const isModalOpen = ref(false);
const isLoadingSubmit = ref(false);
const unassignLoadingIds = ref<number[]>([]);

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

const UnassignCollection = async (collection: { id: number }) => {
    unassignLoadingIds.value.push(collection.id);
    const method = props.data.routes.detach_collections_route.method;
    const url = route(
        props.data.routes.detach_collections_route.name,
        {
            ...props.data.routes.detach_collections_route.parameters,
            collection: collection.id,
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
                    (id) => id !== collection.id
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
                <Link
                    :href="route(data.department.url_master.name, data.department.url_master.parameters)"
                    class="underline font-bold"
                >
                    {{ trans("the master data") }}
                </Link>
            </span>
        </Message>

        <div class="grid grid-cols-8 gap-4 mt-4">
            <!-- Sidebar -->
            <div class="col-span-2">
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <div class="bg-white rounded-lg shadow mb-4 overflow-hidden">
                        <Image
                            v-if="data?.department?.image"
                            :src="data?.department?.image"
                            imageCover
                            class="w-full h-40 object-cover rounded-t-lg"
                        />
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
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <div class="text-xl font-semibold text-gray-800">
                            {{ trans("Collections") }}
                        </div>
                        <Button
                            size="xs"
                            :label="trans('Add Collection')"
                            type="create"
                            @click="isModalOpen = true"
                        />
                    </div>

                    <ul v-if="data.collections.data.length" class="space-y-2">
                        <li
                            v-for="collection in data.collections.data"
                            :key="collection.id"
                            class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition duration-200 p-3 flex items-start gap-3"
                        >
                            <!-- Thumbnail -->
                            <div
                                class="w-10 h-10 bg-gray-100 rounded-md overflow-hidden flex items-center justify-center flex-shrink-0"
                            >
                                <Image
                                    v-if="collection.image?.[0]"
                                    :src="collection.image[0]"
                                    imageCover
                                    class="object-cover w-full h-full"
                                />
                                <FontAwesomeIcon
                                    v-else
                                    :icon="faImage"
                                    class="text-gray-400 w-5 h-5"
                                />
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-medium text-gray-800 truncate">
                                    {{ collection.name }}
                                </h3>
                                <p class="text-xs text-gray-500 line-clamp-2 mt-0.5">
                                    {{ collection.description || trans("No description") }}
                                </p>
                            </div>

                            <!-- Unassign Button -->
                            <Button
                                type="negative"
                                size="xs"
                                class="ml-2"
                                :label="''"
                                :icon="faUnlink"
                                v-tooltip="'Unassign'"
                                :loading="unassignLoadingIds.includes(collection.id)"
                                @click="() => UnassignCollection(collection)"
                            />
                        </li>
                    </ul>

                    <div v-else class="text-sm text-gray-500 italic mt-6">
                        {{ trans("No collections found.") }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <Modal
        :isOpen="isModalOpen"
        @onClose="isModalOpen = false"
        width="w-full max-w-6xl"
    >
        <CollectionSelector
            :headLabel="trans('Add Collection to') + ' ' + data?.department?.name"
            :routeFetch="props.data.routeList.collections_route"
            :isLoadingSubmit="isLoadingSubmit"
            @submit="assignCollection"
        />
    </Modal>
</template>

