<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Family } from "@/types/family";
import { routeType } from "@/types/route";
import { remove as loRemove } from "lodash-es";
import { ref } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Icon from "@/Components/Icon.vue";
import { faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faTrash, faPowerOff, faExclamationTriangle, faTrashAlt, faGameConsoleHandheld } from "@fal";
import { faPlay } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import Dialog from "primevue/dialog";
import ConfirmPopup from "primevue/confirmpopup";
import { useConfirm } from "primevue/useconfirm";
import InputLink from "@/Components/CMS/Fields/Link.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { notify } from "@kyvg/vue3-notification";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { RouteParams } from "@/types/route-params";



library.add(faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay);

const props = defineProps<{
    data: {}
    tab?: string
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    website_domain?: String
}>();

const confirm = useConfirm();
const showOfflineModal = ref(false);
const selectedCollection = ref<any | null>(null);

function openOfflineModal(event: MouseEvent, item: any) {
    const target = event.currentTarget as HTMLElement;

    isConfirmOpen.value = true

    confirm.require({
        target,
        message: 'Are you sure you want to inactive this collection (webpage will set offline)?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Yes',
        rejectLabel: 'Cancel',
        rejectProps: {
            label: "No",
            severity: "secondary",
            outlined: true
        },
        acceptProps: {
            label: "Yes",
            severity: "danger"
        },
        accept: () => {
            selectedCollection.value = item;
            showOfflineModal.value = true;
            isConfirmOpen.value = false
        },
        reject: () => {
            isConfirmOpen.value = false
        },
        onHide: () => {
            isConfirmOpen.value = false
        }
    });
}

// TODO: FIX TS
function collectionRoute(collection: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.collections.show":
        case "grp.org.shops.show.catalogue.collections.index":
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.collections.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    collection.slug
                ]);
        case "grp.overview.catalogue.collections.index":
            return route(
                "grp.org.shops.show.catalogue.collections.show",
                [collection.organisation_slug, collection.shop_slug, collection.slug]);

        case "grp.org.shops.show.catalogue.departments.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.collection.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,
                    collection.slug]);

        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,
                    (route().params as RouteParams).subDepartment,
                    collection.slug]);
        case "grp.org.shops.show.catalogue.families.show.collection.index":
    }
}

function shopRoute(family: Family) {
    switch (route().current()) {
        case "grp.org.shops.index":
            return route(
                "grp.org.shops.show.catalogue.dashboard",
                [(route().params as RouteParams).organisation, family.shop_slug]);
    }
}

function departmentRoute(family: Family) {
    switch (route().current()) {
        case "grp.org.shops.index":
            return route(
                "grp.org.shops.show.catalogue.departments.index",
                [(route().params as RouteParams).organisation, family.shop_slug, family.department_slug]);
    }
}

const isLoadingDetach = ref<string[]>([]);
const reroute = ref("");

function resetModalState() {
    selectedCollection.value = null;
    reroute.value = "";
    showOfflineModal.value = false;
}

const SetOffline = () => {
    if (!selectedCollection.value) return;

    const routeInfo = selectedCollection.value.route_disable_webpage;
    if (!routeInfo) return;

    router.patch(
        route(routeInfo.name, routeInfo.parameters),
        {
            path: reroute.value // ← ini dikirim sebagai data body PATCH
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                resetModalState();
                notify({
                    title: "Success",
                    text: "Webpage rerouted successfully.",
                    type: "success"
                });
            },
            onError: (errors) => {
                console.error("Save failed:", errors);
                notify({
                    title: "Failed to Save",
                    text: "Please check your input and try again.",
                    type: "error"
                });
            }
        }
    );
};


const onErrorDeleteCollection = (error) => {
    console.log(error)
    notify({
        title: "Failed to Delete",
        text: error.webpage ? error.webpage  :"Please check your Collection.",
        type: "error"
    });
}

const isConfirmOpen = ref(false)

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state_icon)="{ item: collection }">
            <Icon :data="collection.state_icon" />
        </template>
        <template #cell(code)="{ item: collection }">
            <Link :href="collectionRoute(collection) as string" class="primaryLink">
                {{ collection["code"] }}
            </Link>
        </template>
        <template #cell(shop_code)="{ item: collection }">
            <Link :href="shopRoute(collection) as string" class="secondaryLink">
                {{ collection["shop_code"] }}
            </Link>
        </template>
        <template #cell(department_code)="{ item: collection }">
            <Link :href="departmentRoute(collection) as string" class="secondaryLink">
                {{ collection["department_code"] }}
            </Link>
        </template>

        <template #cell(state_webpage)="{ item: collection }">
            <div v-if="collection?.state_webpage">
                <Link v-if="collection.state_webpage === 'live'" as="button" :href="collection.url_webpage">
                <div class="flex  w-fit items-center gap-2 text-xs font-medium">
                    <Icon :data="collection.state_webpage_icon" />
                    <span class="cursor-pointer">
                        {{ collection.url_webpage }}
                    </span>
                    </div>
                </Link>
                <div v-if="collection.state_webpage == 'closed'">
                <div class="flex w-fit items-center gap-2 text-xs font-medium">
                    <Icon :data="collection.state_webpage_icon" />
                    <span class="cursor-pointer">
                        {{ collection.url_webpage }}
                    </span>
                    </div>
                </div>
                <Icon v-else :data="collection.state_webpage_icon" />
            </div>
        </template>


        <template #cell(actions)="{ item }">
            <div v-if="!item.state_webpage && item.state_webpage != 'live' && item.state_webpage != 'closed'">
                <Link v-if="item.route_delete_collection " as="button"
                      :href="route(item.route_delete_collection.name, item.route_delete_collection.parameters)"
                      :method="item.route_delete_collection.method" preserve-scroll
                      @start="() => isLoadingDetach.push('detach' + item.id)" @Error="(e)=>onErrorDeleteCollection(e)"
                      @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
                    <Button :icon="faTrashAlt" type="negative" size="xs" v-tooltip="'Delete collection'"
                            :loading="isLoadingDetach.includes('detach' + item.id)" />
                </Link>
            </div>

            <ConfirmPopup>
                <template #icon>
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
                </template>
            </ConfirmPopup>
            <div v-if="item.state_webpage == 'live'">
                <Button :icon="faPowerOff" type="tertiary" size="xs" :key="item.state_webpage"
                    @click="(e) => openOfflineModal(e, item)"
                    v-tooltip="isConfirmOpen ? '' : 'Set collection as inactive'" />
            </div>

            <Link v-if="routes?.detach?.name" as="button" :href="route(routes.detach.name, routes.detach.parameters)"
                  :method="routes.detach.method" :data="{
                    collection: item.id
                }" preserve-scroll @start="() => isLoadingDetach.push('detach' + item.id)"
                  @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
                <Button icon="fal fa-times" type="negative" size="xs" v-tooltip="'Delete collection'"
                        :loading="isLoadingDetach.includes('detach' + item.id)" />
            </Link>
        </template>
    </Table>

    <Dialog v-model:visible="showOfflineModal" modal header="Setup Webpage Rerouting" :style="{ width: '500px' }"
            @hide="resetModalState">
        <div class="text-gray-700 text-sm mb-4">
            You're about to reroute this webpage.<br />
            Please confirm where it should redirect to.
        </div>

        <PureInput :prefix="{label : `${website_domain}/`, icon : null}"  v-model="reroute" class="w-full" >
        </PureInput>

        <div class="flex justify-end mt-4 mb-2 gap-2">
            <Button type="secondary" label="Cancel" @click="resetModalState" />
            <Button type="save" label="Save" @click="SetOffline" :disabled="!reroute" />
        </div>
    </Dialog>

</template>
