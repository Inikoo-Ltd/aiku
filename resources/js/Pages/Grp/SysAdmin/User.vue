<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 14 Sept 2022 02:15:21 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableUserRequestLogs from "@/Components/Tables/Grp/SysAdmin/TableUserRequestLogs.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { faIdCard, faUser, faClock, faDatabase, faEnvelope, faHexagon, faFile, faShieldCheck, faUserTag, faKey, faCopy } from "@fal";
import { faExclamationTriangle, faRoad } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { capitalize } from "@/Composables/capitalize";
import SysadminUserShowcase from "@/Components/Showcases/Grp/SysadminUserShowcase.vue";
import UserPermissions from "@/Components/Sysadmin/UserPermissions.vue";
import UserRoles from "@/Components/Sysadmin/UserRoles.vue";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { Tabs as TSTabs } from "@/types/Tabs";
import type { Component } from "vue";
import UserApiTokens from "@/Components/Sysadmin/UserApiTokens.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";
import { routeType } from "@/types/route";
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import axios from "axios";
import PureInput from "@/Components/Pure/PureInput.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import { useCopyText } from "@/Composables/useCopyText"

library.add(faIdCard, faUser, faClock, faDatabase, faEnvelope, faHexagon, faFile, faRoad, faShieldCheck, faUserTag, faExclamationTriangle, faKey, faCopy);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: {}
    request_logs?: {}
    history?: {}
    permissions?: {}
    roles?: {}
    api_tokens?: {}
    apiRoutes: {
        createToken: routeType
        deleteToken: routeType
    }
}>();

const isModalApiToken = ref(false);

const currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Component = {
        showcase: SysadminUserShowcase,
        api_tokens: UserApiTokens,
        details: ModelDetails,
        request_logs: TableUserRequestLogs,
        history: TableHistories,
        permissions: UserPermissions,
        roles: UserRoles
    };
    return components[currentTab.value];

});

const newToken = ref("");
const isLoadingGenerate = ref(false);
// const isNewRegenerate = ref(false);
const onGenerateApiToken = async () => {
    isLoadingGenerate.value = true;

    // if (isRegenerate) {
    //     isNewRegenerate.value = true;
    // }

    try {
        const data = await axios.post(
            route(props.apiRoutes.createToken.name, props.apiRoutes.createToken.parameters),
            {
                data: "qqq"
            }
        );

        console.log("Generate API Token response:", data.data);
        newToken.value = data.data.token;

        router.reload(
            {
                only: ["api_tokens"]
            }
        );

    } catch (error) {
        console.log("error", error);
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to create API Token"),
            type: "error"
        });
    } finally {
        isLoadingGenerate.value = false;
        // isNewRegenerate.value = false;
    }
}


// Method: On recently copied
const isRecentlyCopied = ref(false)
const onClickCopyButton = async (text: string) => {
    useCopyText(text)
    isRecentlyCopied.value = true
    setTimeout(() => {
        isRecentlyCopied.value = false
    }, 3000)
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Button @click="() => isModalApiToken = true" label="Generate API Token" type="tertiary">

            </Button>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"></component>

    <Modal :isOpen="isModalApiToken" @onClose="() => (isModalApiToken = false)" width="w-fit max-w-xl"
           height="h-[500px]">

        <div class="mt-3">
            <div class="text-center sm:mt-5">
                <div as="h3" class="text-base font-semibold">
                    {{ trans("Generate API Token") }}
                </div>

                <div class="text-sm text-gray-500">
                    {{ trans("You can Generate a new API Token for this user. This token can be used to authenticate API requests.") }}
                </div>
            </div>
        </div>

        <div class="mt-4 mx-auto w-fit">
            <div v-if="newToken" class="w-full max-w-xl">
                <div class="grid w-full max-w- mx-auto xflex items-center gap-x-2">
                    <div class="text-gray-500 text-sm text-center">
                        {{ trans("Here is your new API Token") }}:
                    </div>

                    <div class="w-full max-w-full relative pr-10 overflow-hidden bg-gray-50 border border-gray-200 rounded-md px-3 py-3 text-gray-500 text-sm inline-flex items-center gap-x-2">
                        <FontAwesomeIcon icon="fal fa-key" class="text-gray-400" fixed-width aria-hidden="true" />
                        <span class="truncate">{{ newToken }}</span>

                        <div class="text-gray-700 group flex justify-center items-center absolute right-2 inset-y-0 gap-x-1"
                            xclass="align === 'right' ? 'left-0' : 'right-0'"
                        >
                            <Transition name="spin-to-down">
                                <FontAwesomeIcon v-if="isRecentlyCopied" icon='fal fa-check' class='text-green-500 px-3 h-full text-xxs leading-none ' fixed-width aria-hidden='true' />
                                <FontAwesomeIcon v-else @click="() => onClickCopyButton(newToken)" icon="fal fa-copy" class="px-3 h-full text-xxs leading-none opacity-50 group-hover:opacity-100 group-active:opacity-100 cursor-pointer" fixed-width aria-hidden="true" />
                            </Transition>
                        </div>
                    </div>


                    <!-- <div @click="() => onGenerateApiToken(true)" v-tooltip="trans('Regenerate API Token')" class="text-gray-400 hover:text-gray-700 cursor-pointer">
                        <LoadingIcon v-if="isNewRegenerate" />
                        <FontAwesomeIcon v-else icon="fal fa-sync-alt" class="" fixed-width aria-hidden="true" />
                    </div> -->
                </div>

                <div class="mt-2 text-amber-500 text-sm items-center gap-x-2 text-center">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-lg" fixed-width aria-hidden="true" />
                    <span class="text-center">{{ trans("Put this token in a safe place, you won't be able to see it again.") }}</span>
                </div>
            </div>

            <Button
                v-else

                @click="onGenerateApiToken"
                label="Click to Generate"
                type="tertiary"
                :loading="isLoadingGenerate"
            />
        </div>
    </Modal>
</template>
