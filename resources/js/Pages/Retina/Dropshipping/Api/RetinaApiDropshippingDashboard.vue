<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { useCopyText } from "@/Composables/useCopyText"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { Head, Link, router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "lodash"
import { computed, ref } from "vue"
import type { Component } from "vue";

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {  } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import RetinaTableApiKey from "@/Components/Tables/Retina/RetinaTableApiKey.vue"
import { routeType } from "@/types/route"
import { Table as TSTable } from '@/types/Table'
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
// import { Message } from "primevue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
library.add()

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	api_tokens?: TSTable
	history?: TSTable
    tabs:{
        current: string
		navigation: {}
    }
	// dataTable: {

	// }
	routes: {
		create_token: routeType
	}
	is_need_to_add_card: boolean
}>()

const isModalApiToken = ref(false);


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
            route(props.routes.create_token.name, props.routes.create_token.parameters),
            {
                data: "qqq"
            }
        );

        console.log("Generate API Token response:", data.data)
        newToken.value = data.data.token;

        router.reload(
            {
                only: ["api_tokens"]
            }
        );

    } catch (error) {
        console.log("error", error)
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to create API Token"),
            type: "error"
        });
    } finally {
        isLoadingGenerate.value = false
    }
}


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
// Method: On recently copied
const isRecentlyCopied = ref(false)
const onClickCopyButton = async (text: string) => {
    useCopyText(text)
    isRecentlyCopied.value = true
    setTimeout(() => {
        isRecentlyCopied.value = false
    }, 3000)
}

const component = computed(() => {
    const components: Component = {
        api_tokens: RetinaTableApiKey,
        history: TableHistories,
    };
    return components[currentTab.value];

});

</script>

<template>
	<Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
		<template #otherBefore>
			<Button @click="() => isModalApiToken = true" label="Generate API Token" type="tertiary">

            </Button>
		</template>
	</PageHeading>
	
	<!-- Section: warning to add card -->
	<div v-if="is_need_to_add_card" class="bg-yellow-100 border border-yellow-500 mx-4 my-2 px-4 py-1 rounded">
        <div class="flex justify-between w-full">
			<div class="flex items-center gap-x-2 text-yellow-700">
				<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500 text-lg" fixed-width aria-hidden="true" />
				{{ trans("You have no cards saved yet.") }}
			</div>
			
			<ButtonWithLink
				:label="trans('Add card')"
				icon="fas fa-plus"
				:routeTarget="{
					'name': 'retina.dropshipping.mit_saved_cards.create',
				}"
				type="warning"
			/>
		</div>
    </div>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"></component>


	<!-- <RetinaTableApiKey
		:data="data.api_tokens"
		:tab="tabs.current"
	/> -->

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
