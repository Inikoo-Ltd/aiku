<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import DummyComponent from "@/Components/DummyComponent.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import Textarea from "primevue/textarea"
import Select from "primevue/select"

import { PageHeading as TSPageHeading } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from '@/Composables/useFormatTime'
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

const stats = [
    { id: 1, name: trans('Created at'), value: useFormatTime(props.data?.created_at) },
    { id: 2, name: trans('Type'), value: props.data.type },
    // { id: 3, name: 'Uptime guarantee', value: '99.9%' },
    // { id: 4, name: 'Paid out to creators', value: '$70M' },
]
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
    <div class="w-full max-w-xl mx-auto my-8">
        
        <div class="block text-sm font-medium text-gray-700">
            <FontAwesomeIcon v-if="data.in_registration_required" icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{ data.label }}
        </div>
        
        <div class="mt-2" xclass="form.errors?.[`poll_replies.${idx}`] ? 'errorShake' : ''">
            <Select
                v-if="data.type === 'option'"
                xv-model="form.poll_replies[idx].answer"
                :modelValue="'ewewqewqeq'"
                xupdate:model-value="(e) => form.clearErrors(`poll_replies.${idx}`)"
                :options="data.options"
                optionLabel="label"
                optionValue="id"
                :placeholder="`Please Choose One`"
                class="w-full" />
            <Textarea
                v-else
                :modelValue="'hehehehe'"
                xupdate:model-value="(e) => form.clearErrors(`poll_replies.${idx}`)"
                rows="5"
                cols="30"
                placeholder="Your answer…"
                class="w-full border rounded-md p-2" />
        </div>
    </div>

    <hr class="my-5 border border-gray-200" />

    <div class="xmx-auto grid max-w-7xl lg:grid-cols-2">
        <div class="px-6 xpb-24 xpt-16 xsm:pb-32 xsm:pt-20 lg:px-8 xlg:pt-32">
            <div class="xmx-auto max-w-2xl lg:mr-0 lg:max-w-lg">
                <!-- <h2 class="text-base/8 font-semibold text-indigo-600">Our track record</h2>
                <p class="mt-2 text-pretty text-4xl font-semibold tracking-tight sm:text-5xl">Trusted by thousands of creators worldwide</p>
                <p class="mt-6 text-lg/8 text-gray-600">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Maiores impedit perferendis suscipit eaque, iste dolor cupiditate blanditiis ratione.</p> -->
                <dl class="mt-16 grid max-w-xl grid-cols-1 gap-8 sm:mt-20 sm:grid-cols-2 xl:mt-16">
                    <div v-for="stat in stats" :key="stat.id" class="flex flex-col gap-y-3 border-l border-gray-900/10 pl-6">
                        <dt class="text-sm/6 text-gray-600">{{ stat.name }}</dt>
                        <dd class="order-first text-2xl font-semibold tracking-tight">{{ stat.value }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>