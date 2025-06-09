<script setup lang="ts">
import SetVisibleList from "@/Components/Departement&Family/SetVisibleList.vue";
import { faImage } from "@far";
import { faInfoCircle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Image from "@/Components/Image.vue";
import Message from "primevue/message";
import { Link } from "@inertiajs/vue3";
import { routeType } from "@/types/route";
import { trans } from "laravel-vue-i18n";
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection } from "@fal";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from '@/Components/Utils/Modal.vue'
import CollectionSelector from "@/Components/Departement&Family/CollectionSelector.vue";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        department: {
            data: {
                name: string,
                description: string,
                image: Array<string>,
                url_master: routeType
            }
        },
        routeList: {
            collectionRoute: routeType,
            collections_route: routeType
        },
        routes: {
            attach_collections_route: routeType,
            detach_collections_route: routeType
        }
    }
}>();

const links = ref([
    {
        label: trans("Create Collection"),
        route_target: props.data.routeList.collectionRoute,
        icon: faAlbumCollection
    }
]);

const isLoadingSubmit = ref(false);
const isModalOpen = ref(false);

const assignCollection = async (collections: any[]) => {
    const method = props.data.routes.attach_collections_route.method;
    const url = route(
        props.data.routes.attach_collections_route.name,
        props.data.routes.attach_collections_route.parameters
    );

    // Kirim hanya array ID
    const collectionIds = collections.map(c => c.id);

    router[method](url, { collections: collectionIds }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            notify({
                title: trans("Something went wrong."),
                text: error?.products || trans("Failed to add collection."),
                type: "error"
            });
        },
        onSuccess: () => {
            notify({
                title: trans("Success!"),
                text: trans("Successfully added portfolios"),
                type: "success"
            });
            isModalOpen.value = false;
        },
        onFinish: () => {
            isLoadingSubmit.value = false;
        }
    });
}

</script>

<template>
    <div class="px-4 pb-8 m-5">

        <Message v-if="data.department?.url_master" severity="success" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">Right Now you follow
                <Link :href="route(data.department.url_master.name, data.department.url_master.parameters)"
                    class="underline font-bold">
                the master data
                </Link>
            </span>
        </Message>


        <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 ">
            <div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <!-- <div class="flex justify-between items-center border-b pb-4 mb-4">
                        <h3 class="text-xl font-semibold">Preview {{data?.department?.name }}</h3>
                    </div> -->
                    <div class="flex items-center justify-between mb-6">
                        <!--  <button @click="goToPrev" aria-label="Previous">
                        <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
                      </button> -->
                        <div class="flex-1 mx-4">
                            <div class="bg-white rounded-lg shadow hover:shadow-md transition duration-300">
                                <Image v-if="data?.department?.image" :src="data?.department?.image" :imageCover="true"
                                    class="w-full h-40 object-cover rounded-t-lg" />
                                <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                                    <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                                </div>
                            </div>
                        </div>
                        <!--  <button @click="goToNext" aria-label="Next">
                        <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
                      </button> -->
                    </div>


                    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
                        <div class="text-sm font-medium">
                            <span>{{ data?.department?.name || "No label" }}</span>
                        </div>
                        <div class="text-md">
                            <span class="text-gray-400">{{ data?.department?.description || "No description" }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="bg-white flex justify-end">
                <div class="w-64 border border-gray-300 rounded-md p-2 h-fit">
                    <div v-for="(item, index) in links" :key="index" class="p-2">
                        <ButtonWithLink :routeTarget="item.route_target" full :icon="item.icon" :label="item.label"
                            type="secondary" />
                    </div>
                    <div class="p-2">
                        <Button full :label="'Add Collection'" @click="isModalOpen = true" />
                    </div>

                </div>
            </div>
        </div>
    </div>


    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-full max-w-6xl">
        <CollectionSelector :headLabel="trans('Add Collection to') + ' ' + data?.department?.name"
            :routeFetch="props.data.routeList.collections_route" :isLoadingSubmit="isLoadingSubmit"
            @submit="assignCollection" />
    </Modal>

</template>
