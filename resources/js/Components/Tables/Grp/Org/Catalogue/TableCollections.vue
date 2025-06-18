<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { routeType } from "@/types/route";
import { remove as loRemove } from "lodash-es";
import { ref, watch, nextTick } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Icon from "@/Components/Icon.vue";
import { faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faPowerOff, faExclamationTriangle, faTrashAlt, faFolders, faFolderTree, faGameConsoleHandheld } from "@fal";
import { faPlay } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import Dialog from "primevue/dialog";
import ConfirmPopup from "primevue/confirmpopup";
import { useConfirm } from "primevue/useconfirm";
import PureInput from "@/Components/Pure/PureInput.vue";
import { notify } from "@kyvg/vue3-notification";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { RouteParams } from "@/types/route-params";
import { Collection } from "@/types/collection";
import collection from "@/Pages/Grp/Org/Catalogue/Collection.vue";
import { trans } from "laravel-vue-i18n";
import SelectQuery from "@/Components/SelectQuery.vue";


library.add(faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay, faFolders, faFolderTree);

const props = defineProps<{
    data: {}
    tab?: string
    routes: {
        indexWebpage : routeType
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    website_domain?: string
}>();
console.log('props',props)
const confirm = useConfirm();
const showOfflineModal = ref(false);
const selectedCollection = ref<any | null>(null);

function openOfflineModal(event: MouseEvent, item: any) {
    const target = event.currentTarget as HTMLElement;

    isConfirmOpen.value = true;

    confirm.require({
        target,
        message: "Are you sure you want to inactive this collection (webpage will set offline)?",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Yes",
        rejectLabel: "Cancel",
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
            isConfirmOpen.value = false;
        },
        reject: () => {
            isConfirmOpen.value = false;
        },
        onHide: () => {
            isConfirmOpen.value = false;
        }
    });
}

// TODO: FIX TS
function collectionRoute(collection: {}) {
    const currentRoute = route().current();

    if (currentRoute === "grp.org.shops.show.catalogue.collections.show" ||
        currentRoute === "grp.org.shops.show.catalogue.collections.index" ||
        currentRoute === "grp.org.shops.show.catalogue.dashboard") {
        return route(
            "grp.org.shops.show.catalogue.collections.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                collection.slug
            ]);
    } else if (currentRoute === "grp.overview.catalogue.collections.index") {
        return route(
            "grp.org.shops.show.catalogue.collections.show",
            [collection.organisation_slug, collection.shop_slug, collection.slug]);
    } else if (currentRoute === "grp.org.shops.show.catalogue.departments.show.collection.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.collection.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).department,
                collection.slug]);
    } else if (currentRoute === "grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).department,
                (route().params as RouteParams).subDepartment,
                collection.slug]);
    }
    // The empty case for "grp.org.shops.show.catalogue.families.show.collection.index" is omitted as it had no implementation
}

function shopRoute(collection: Collection) {
        return route(
            "grp.org.shops.show.catalogue.collections.index",
            [collection.organisation_slug, collection.shop_slug]);

}

function organisationRoute(collection: Collection) {
    return route(
        "grp.org.overview.collections.index",
        [collection.organisation_slug]);

}

function webpageRoute(collection: Collection) {
    return route(
        "grp.org.shops.show.web.webpages.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            collection.website_slug,
            collection.webpage_slug

        ]
    );

}

function parentRoute(slug: string) {

    return route(
        "grp.helpers.redirect_collections_in_product_category",
        [
            slug
        ]
    );

}


function departmentRoute(family: Collection) {
    if (route().current() === "grp.org.shops.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.index",
            [(route().params as RouteParams).organisation, collection.shop_slug, collection.department_slug]);
    }
}

const isLoadingDetach = ref<string[]>([]);
const reroute = ref<{ url: string | null }>({ url: null });

function resetModalState() {
    selectedCollection.value = null;
    reroute.value = { url: null };
    showOfflineModal.value = false;
}

const SetOffline = () => {
    if (!selectedCollection.value) return;

    const routeInfo = selectedCollection.value.route_disable_webpage;
    if (!routeInfo) return;

    const raw = reroute.value.url?.trim();
    const payload = raw && raw !== "" ? raw : "/";

    router.patch(
        route(routeInfo.name, routeInfo.parameters),
        {
            path: payload
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
    console.log(error);
    notify({
        title: "Failed to Delete",
        text: error.webpage ? error.webpage : "Please check your Collection.",
        type: "error"
    });
};

const isConfirmOpen = ref(false);

function handleUrlChange(e: string | null) {
  const raw = e?.trim() ?? "";
  reroute.value.url = raw ? raw : "/"; // always updates url, never overwrites reroute
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(organisation_code)="{ item: collection }">
            <Link :href="organisationRoute(collection) as string" class="secondaryLink">
                {{ collection["organisation_code"] }}
            </Link>
        </template>
        <template #cell(shop_code)="{ item: collection }">
            <Link :href="shopRoute(collection) as string" class="secondaryLink">
                {{ collection["shop_code"] }}
            </Link>
        </template>

        <template #cell(state_icon)="{ item: collection }">
            <Icon :data="collection.state_icon" />
        </template>
        <template #cell(code)="{ item: collection }">
            <Link :href="collectionRoute(collection) as string" class="primaryLink">
                {{ collection["code"] }}
            </Link>
        </template>


        <template #cell(department_code)="{ item: collection }">
            <Link :href="departmentRoute(collection) as string" class="secondaryLink">
                {{ collection["department_code"] }}
            </Link>
        </template>

        <template #cell(parents)="{ item: collection }">

            <template v-for="(parent, index) in collection.parents_data" :key="index">
                <FontAwesomeIcon v-if="parent.type === 'department'" :icon="faFolderTree" class="mr-1" v-tooltip="trans('Department')" />
                <FontAwesomeIcon v-else-if="parent.type === 'subdepartment'" :icon="faFolders" class="mr-1" v-tooltip="trans('Sub Department')" />
                <Link :href="parentRoute(parent.slug) as string" class="secondaryLink">
                    {{ parent.code && parent.code.length > 6 ? parent.code.substring(0, 6) + "..." : parent.code }}
                </Link>


            </template>


        </template>

        <template #cell(webpage)="{ item: collection }">

            <template v-if="collection.webpage_slug">
                <Icon :data="collection.webpage_state_icon" :Tooltip="collection.webpage_state_label" />

                <Link as="button" :href="webpageRoute(collection) as string" class="secondaryLink ml-2">

                    <div class="flex  w-fit items-center gap-2 ">
                        <span class="cursor-pointer">
                        {{ collection.webpage_url && collection.webpage_url.length > 16 ? collection.webpage_url.substring(0, 16) + "..." : collection.webpage_url }}
                    </span>
                    </div>
                </Link>
            </template>


        </template>


        <template #cell(actions)="{ item }">
            <div v-if="!item.webpage_state && item.webpage_state != 'live' && item.webpage_state != 'closed'">
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
            <div v-if="item.webpage_state == 'live'">
                <Button :icon="faPowerOff" type="tertiary" size="xs" :key="item.webpage_state"
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
            @hide="resetModalState" :contentStyle="{ overflowY: 'visible'}">
        <div class="text-gray-700 text-sm mb-4">
            You're about to reroute this webpage.<br />
            Please confirm where it should redirect to.
        </div>

        <!-- <PureInput tabindex="0" ref="rerouteInputRef" :prefix="{label : `${website_domain}/`, icon : null}" v-model="reroute" class="w-full">
        </PureInput> -->

        <SelectQuery
            :urlRoute="route(routes?.indexWebpage?.name, routes?.indexWebpage?.parameters)"
            :value="reroute"
            :placeholder="'Select url'"
            :required="true"
            :trackBy="'href'"
            :label="'href'"
            :valueProp="'url'"
            :closeOnSelect="true"
            :clearOnSearch="false"
            :fieldName="'url'"
           :onChange="handleUrlChange"
        />

        {{ reroute.url }}
        <div class="flex justify-end mt-4 mb-2 gap-2">
            <Button type="secondary" label="Cancel" @click="resetModalState" />
            <Button type="save" label="Save" @click="SetOffline"  />
        </div>
    </Dialog>

</template>
