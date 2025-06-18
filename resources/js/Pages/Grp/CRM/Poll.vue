<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import DummyComponent from "@/Components/DummyComponent.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as TSPageHeading } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faTrashAlt)

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: TSPageHeading
    // tabs: TSTabs
    data: {}

    
}>()

// const currentTab = ref(props.tabs.current)
// const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

// const component = computed(() => {

//     const components: Component = {
//         showcase: DummyComponent
//     }

//     return components[currentTab.value]

// })

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <ModalConfirmationDelete
                :routeDelete="{
                    name: 'grp.models.poll.delete',
                    parameters: {
                        poll: data.id
                    }
                }"
                :title="trans('Are you sure you want to this poll?')"
                isFullLoading
            >
                <template #default="{ isOpenModal, changeModel }">
                    <Button
                        @click="changeModel"
                        icon="fal fa-trash-alt"
                        type="negative"
                    />
                </template>
            </ModalConfirmationDelete>

        </template>
    </PageHeading>
    <!-- <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" /> -->

    <!-- <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" /> -->
    <pre>{{ data }}</pre>
</template>