<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 Sept 2025 14:19:16 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router, Link } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faSmileWink,
    faRecycle,
    faCube,
    faChair,
    faHandPaper,
    faExternalLink,
    faFolder,
    faBoxCheck,
    faPrint,
    faExchangeAlt,
    faUserSlash,
    faTired,
    faFilePdf,
    faExclamationTriangle
} from "@fal";
import { faArrowRight, faCheck, faSave } from "@fas";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { Tabs as TSTabs } from "@/types/Tabs";
import AlertMessage from "@/Components/Utils/AlertMessage.vue";
import { computed, provide, ref, onMounted } from "vue";
import type { Component } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import BoxStatsDeliveryNote from "@/Components/Warehouse/DeliveryNotes/BoxStatsDeliveryNote.vue";
import TableDeliveryNoteItems from "@/Components/Warehouse/DeliveryNotes/TableDeliveryNoteItemsForReplacement.vue";
import TablePickings from "@/Components/Warehouse/DeliveryNotes/TablePickings.vue";
import { routeType } from "@/types/route";
import Tabs from "@/Components/Navigation/Tabs.vue";
import type { DeliveryNote } from "@/types/warehouse";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { notify } from "@kyvg/vue3-notification";
import Message from 'primevue/message';
import { debounce } from "lodash-es";


library.add(faSmileWink, faRecycle, faTired, faFilePdf, faFolder, faBoxCheck, faPrint, faExchangeAlt, faUserSlash, faCube, faChair, faHandPaper, faExternalLink, faArrowRight, faCheck, faSave);

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    items?: {}
    pickings?: {}
    warning?: {
        text: string
        picking_sessions: routeType
    }
    alert?: {
        status: string
        title?: string
        description?: string
    }
    delivery_note: DeliveryNote
    is_collection: boolean
    notes?: {
        note_list: {
            label: string
            note: string
            editable?: boolean
            bgColor?: string
            textColor?: string
            color?: string
            lockMessage?: string
            field: string  // customer_notes, public_notes, internal_notes
        }[]
        // updateRoute: routeType
    }

    box_stats: {}
    routes: {
        update: routeType

    }
    address: {
        delivery: {}
        options: {
            countriesAddressData: {
                id: number
                name: string
                code: string
            }[]
        }
    }
    warehouse: {
        slug: string
    }
}>();


const currentTab = ref(props.tabs?.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);
const component = computed(() => {
    const components: Component = {
        items: TableDeliveryNoteItems,
        pickings: TablePickings
    };

    return components[currentTab.value];
});



const listError = ref({
    box_stats_parcel: false
});
provide("listError", listError.value);

const showWarningMessage = ref(true);


const debReloadPage = debounce(() => {
    router.reload({
        except: ['auth', 'breadcrumbs', 'flash', 'layout', 'localeData', 'pageHead', 'ziggy']
    })
}, 1200)

const selectSocketBasedPlatform = (porto) => {
    return {
        event: `grp.dn.${porto.id}`,
        action: '.dn-note-update'
    }
}

onMounted(() => {
    const socketConfig = selectSocketBasedPlatform(props.delivery_note)

    if (!socketConfig) {
        console.warn('Socket config not found for platform:', props.delivery_note.id)
        return
    }

    const channel = window.Echo
        .private(socketConfig.event)
        .listen(socketConfig.action, (eventData: any) => {
            debReloadPage()
        })
    console.log('Subscribed to channel for porto ID:', props.delivery_note.id, 'Channel:', channel)
})

// Section: Handle quantity to resend changes
const quantityToResendData = ref<{ [key: string]: number }>({});
const validationErrorsData = ref<{ [key: string]: boolean }>({});
const loadingCreateReplacement = ref(false)

const handleQuantityToResendUpdate = (itemId: string | number, value: number) => {
    quantityToResendData.value[itemId] = value;
};

const handleValidationError = (itemId: string | number, hasError: boolean) => {
    if (hasError) {
        validationErrorsData.value[itemId] = true;
    } else {
        delete validationErrorsData.value[itemId];
    }
};

// Computed property to check if the replacement button should be disabled
const isReplacementDisabled = computed(() => {
    const quantities = Object.values(quantityToResendData.value);
    const hasValidationErrors = Object.keys(validationErrorsData.value).length > 0;
    
    // Disable if:
    // 1. No quantities or all quantities are 0
    // 2. There are validation errors
    return (quantities.length === 0 || quantities.every(quantity => quantity === 0)) || hasValidationErrors;
});

// Section: Create Replacement
const onCreateReplacement = (action: any) => {
    loadingCreateReplacement.value = true
    // Filter items have quantity > 0
    const delivery_note_items = Object.entries(quantityToResendData.value)
        .filter(([itemId, quantity]) => quantity > 0)
        .map(([itemId, quantity]) => ({
            id: parseInt(itemId),
            quantity: quantity
        }));

    if (delivery_note_items.length === 0) {
        notify({
            title: trans("No items selected"),
            text: trans("Please select at least one item with quantity to resend"),
            type: "warning"
        });
        return;
    }

    const payload = { delivery_note_items };

    console.log('Creating replacement with payload:', payload);

    // Submit replacement request
    router[action.route.method](
        route(action.route.name, action.route.parameters),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Replacement delivery note created successfully"),
                    type: "success"
                });
                // Reset quantity data after successful submission
                quantityToResendData.value = {};
                loadingCreateReplacement.value = false
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message || trans("Failed to create replacement delivery note"),
                    type: "error"
                });
            }, 
            onFinish: () => {
                loadingCreateReplacement.value = false
            }
        }
    );
};

</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" isButtonGroupWithBorder>
        <template #button-action-replacement="{action}">
            <Button @click="() => onCreateReplacement(action)" :label="action.label" :icon="action.icon"
                :type="action.type" :disabled="isReplacementDisabled" :loading="loadingCreateReplacement" />
        </template>
    </PageHeading>

    <div v-if="alert?.status" class="p-2 pb-0">
        <AlertMessage :alert />
    </div>

    <div v-if="warning && showWarningMessage" class="p-1">
        <Message severity="warn" class="p-1 rounded-md border-l-4 border-yellow-500 bg-yellow-50 text-yellow-800"
            :closable="true" @close="showWarningMessage = false">
            <div class="flex items-start gap-3">
                <!-- Icon -->
                <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500 w-4 h-4 flex-shrink-0" />

                <!-- Main Content -->
                <div class="flex gap-2 flex-wrap items-center">
                    <!-- Warning Text -->
                    <div class="text-sm font-medium">
                        {{ warning?.text }}
                    </div>

                    <!-- Session Links in One Line -->
                    <div class="flex flex-wrap items-center gap-2 font-bold underline">
                        <template v-for="(item, idx) in warning?.picking_sessions" :key="idx">
                            <Link :href="route(item.route.name, item.route.parameters)" class="text-sm hover:underline">
                            {{ item.reference }}
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </Message>
    </div>



    <BoxStatsDeliveryNote v-if="box_stats" :boxStats="box_stats" :routes :deliveryNote="delivery_note"
        :updateRoute="routes.update" :shipments />

    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <div class="pb-12">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :routes
            :state="delivery_note.state" @update:quantity-to-resend="handleQuantityToResendUpdate" 
            @validation-error="handleValidationError" />
    </div>


</template>
