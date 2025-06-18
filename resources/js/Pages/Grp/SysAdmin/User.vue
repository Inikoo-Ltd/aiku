<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 14 Sept 2022 02:15:21 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, defineAsyncComponent, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableUserRequestLogs from "@/Components/Tables/Grp/SysAdmin/TableUserRequestLogs.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { faIdCard, faUser, faClock, faDatabase, faEnvelope, faHexagon, faFile, faShieldCheck, faUserTag, faKey, faSyncAlt } from '@fal'
import { faExclamationTriangle } from '@fas'
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"
import { faRoad } from "@fas"
import SysadminUserShowcase from '@/Components/Showcases/Grp/SysadminUserShowcase.vue'
import UserPermissions from '@/Components/Sysadmin/UserPermissions.vue'
import UserRoles from '@/Components/Sysadmin/UserRoles.vue'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import type { Component } from 'vue'
import UserApiTokens from '@/Components/Sysadmin/UserApiTokens.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'
import { useCopyText } from '@/Composables/useCopyText'
import PureInput from '@/Components/Pure/PureInput.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
library.add(faIdCard, faUser, faClock, faDatabase, faEnvelope, faHexagon, faFile, faRoad, faShieldCheck, faUserTag, faExclamationTriangle, faKey, faSyncAlt)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

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
}>()

const isModalApiToken = ref(false)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: SysadminUserShowcase,
        api_tokens: UserApiTokens,
        details: ModelDetails,
        request_logs: TableUserRequestLogs,
        history: TableHistories,
        permissions: UserPermissions,
        roles: UserRoles,
    }
    return components[currentTab.value]

})

const newToken = ref('')
const isLoadingGenerate = ref(false)
const isNewRegenerate = ref(false)
const onGenerateApiToken = async (isRegenerate?: boolean) => {
    isLoadingGenerate.value = true

    if (isRegenerate) {
        isNewRegenerate.value = true
    }

    try {
        const data = await axios.post(
            route(props.apiRoutes.createToken.name, props.apiRoutes.createToken.parameters),
            {
                data: 'qqq'
            }
        )

        console.log('Generate API Token response:', data.data)
        newToken.value = data.data.token
        
        router.reload(
        {
            only: ['api_tokens']
        }
    )
        
    } catch (error) {
        console.log('error', error)
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to set location"),
            type: "error"
        })
    } finally {
        isLoadingGenerate.value = false
        isNewRegenerate.value = false
    }

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

    <Modal :isOpen="isModalApiToken" @onClose="() => (isModalApiToken = false)" width="w-full max-w-xl"
        height="h-[500px]">

        <div>
            <!-- <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
                xxx
            </div> -->
            <div class="mt-3 text-center sm:mt-5">
                <div as="h3" class="text-base font-semibold">
                    Generate API Token
                </div>

                <div class="text-sm text-gray-500">
                    You can Generate a new API Token for this user. This token can be used to authenticate API requests.
                </div>
            </div>
        </div>

        <div class="mt-4 mx-auto w-fit">
            <div v-if="newToken" class="w-full max-w-xl">
                <div class="w-full max-w-64 mx-auto flex items-center gap-x-2">
                    <PureInput
                        v-model="newToken"
                        disabled
                        copyButton
                        :styleInput="{
                            paddingRight: '32px'
                        }"
                    />
                    <div @click="() => onGenerateApiToken(true)" v-tooltip="trans('Regenerate API Token')" class="text-gray-400 hover:text-gray-700 cursor-pointer">
                        <LoadingIcon v-if="isNewRegenerate" />
                        <FontAwesomeIcon v-else icon="fal fa-sync-alt" class="" fixed-width aria-hidden="true" />
                    </div>
                </div>

                <div class="text-orange-500 text-sm xflex items-center gap-x-2 text-center">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="xopacity-70 text-lg" fixed-width aria-hidden="true" />
                    <span class="text-center">Put this token in a safe place, you won't be able to see it again.</span>
                    <!-- <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="opacity-70 text-lg" fixed-width aria-hidden="true" /> -->
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
