<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import Button from "@/Components/Elements/Buttons/Button.vue";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faAsterisk, faTrashAlt } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { useFormatTime } from "@/Composables/useFormatTime";
import TablePollOptions from "@/Components/Tables/Grp/Org/CRM/TablePollOptions.vue";
import type { Component } from "vue";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { PageHeadingTypes } from "@/types/PageHeading"
import { faExclamationCircle } from "@fas";

library.add(faTrashAlt);


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    data: {
        id: number
        label: string
        type: string
        type_value: string
        in_registration: boolean
        in_registration_required: boolean
        created_at: string
        options?: { id: number, label: string }[]
        poll_replies?: { answer?: string, idx?: number }[]
    }
    showcase: {}
    poll_options: {}


}>();

const stats = [
    { id: 1, name: trans("Created at"), value: useFormatTime(props.data?.created_at) },
    { id: 2, name: trans("Type"), value: props.data.type }
];


let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Record<string, Component> = {
        poll_options: TablePollOptions
    };

    if (currentTab.value === 'poll_options' && !props.poll_options) {
        return null;
    }

    return components[currentTab.value];
});

</script>


<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <div v-if="props.data.in_registration_required"
                class="flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-sm text-red-600">
                <FontAwesomeIcon :icon="faExclamationCircle" class="animate-pulse" />
                <span>{{ trans("Required in registration") }}</span>
            </div>
        </template>

        <template #otherBefore>
            <ModalConfirmationDelete :routeDelete="{
                name: 'grp.models.poll.delete',
                parameters: {
                    poll: data.id
                }
            }" :title="trans('Are you sure you want to delete this poll?')" isFullLoading>
                <template #default="{ changeModel }">
                    <Button @click="changeModel" icon="fal fa-trash-alt" type="negative" />
                </template>
            </ModalConfirmationDelete>
        </template>
    </PageHeading>

    <div class="">

        <!-- Preview Card -->
        <div class=" max-w-2xl my-3 mx-4 rounded border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        {{ trans("Preview") }}
                    </div>

                    <div class="mt-1 text-sm text-gray-500">
                        {{ trans("Registration form appearance") }}
                    </div>
                </div>

                <div
                    class="shrink-0 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-medium text-gray-600">
                    {{
                        data.type_value === "option"
                            ? trans("Multiple Choice")
                            : trans("Open Answer")
                    }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4"
                :class="data.in_registration ? 'opacity-70' : ''">
                <!-- Label -->
                <div class="mb-3 flex items-start gap-2">
                    <FontAwesomeIcon v-if="data.in_registration_required" icon="fas fa-asterisk"
                        class="mt-1 text-[9px] text-red-500" />

                    <label class="text-sm font-medium leading-5 text-gray-800">
                        {{ data.label }}
                    </label>
                </div>

                <!-- Input -->
                <Select v-if="data.type_value === 'option'" :modelValue="null" :options="data.options"
                    optionLabel="label" optionValue="id" :placeholder="trans('Please choose one')" class="w-full" />

                <Textarea v-else rows="4" :placeholder="trans('Your answer...')"
                    class="w-full rounded-xl border border-gray-200 p-3 text-sm" />

                <!-- Meta -->
                <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-3">
                    <div class="text-xs text-gray-500">
                        {{ data.type }}
                    </div>

                    <div class="flex items-center gap-2">
                        <div v-if="data.in_registration_required"
                            class="rounded-full bg-red-100 px-2 py-1 text-[11px] font-medium text-red-600">
                            {{ trans("Required") }}
                        </div>

                        <div v-if="data.in_registration"
                            class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-700">
                            {{ trans("Registration") }}
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Tabs -->
        <template v-if="data.type !== 'open_question'">
            <div class=" bg-white border-t">
                <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

                <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"
                    :handleTabUpdate class="py-6" />
            </div>
        </template>
    </div>
</template>