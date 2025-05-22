<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 17 Sept 2022 00:32:56 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableLocations from "@/Components/Tables/Grp/Org/Inventory/TableLocations.vue"
import { capitalize } from "@/Composables/capitalize"
import { faWarehouse, faMapSigns } from '@fal'
import { faFileExport } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from "@/Components/Elements/Buttons/Button.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import { ref } from "vue"
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import { Menu, MenuButton, MenuItems } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { UploadPallet } from '@/types/Pallet'

library.add(faWarehouse, faMapSigns, faFileExport)

interface UploadSection {
    title: {
        label: string
        information: string
    }
    progressDescription: string
    upload_spreadsheet: UploadPallet
    preview_template: {
        header: string[]
        rows: {}[]
    }
}

const props = defineProps<{
    title: string
    pageHead: {}
    data: {}
    export: {
        route: routeType
        columns: {
            label: string
            value: string
        }[]
    }
    upload_locations: UploadSection

}>()

const isModalUploadOpen = ref(false)

const selectedColumnExport = ref<string[]>([])
const onClickColumnExport = (column: string) => {
    if (selectedColumnExport.value.includes(column)) {
        selectedColumnExport.value = selectedColumnExport.value.filter((item) => item !== column)
    } else {
        selectedColumnExport.value.push(column)
    }
}
const isExporting = ref(false)
const progressExport = ref(0)
const onExport = () => {
    router[props.export.method || 'get'](
        route(props.export.route.name, {...props.export.route.parameters, columns: selectedColumnExport.value.join(',')}), {
            onStart: () => {
                isExporting.value = true
            },
            onProgress: (progress: number) => {
                progressExport.value = progress
            },
            onFinish: async (response) => {
                isExporting.value = false
                progressExport.value = 0

                if (response?.data?.file) {
                    const fileBlob = new Blob([response.data.file], { type: 'application/octet-stream' });
                    const downloadLink = document.createElement('a');
                    downloadLink.href = URL.createObjectURL(fileBlob);
                    downloadLink.download = 'exported-file.csv'; // or dynamically determine the filename
                    downloadLink.click();
                }
            },
        }

    )
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Menu v-slot="{ close }" as="div" class="relative inline-block text-left">
                <div>
                    <MenuButton class="">
                        <Button
                            type="tertiary"
                            icon="fas fa-file-export"
                            :loading="isExporting"
                        >
                            <template #label>
                                <div>
                                    {{ trans('Export') }}
                                    <span v-if="isExporting">
                                        {{ progressExport }}/100%
                                    </span>
                                </div>
                            </template>
                        </Button>
                    </MenuButton>
                </div>

                <transition name="headlessui2">
                    <MenuItems class="w-64 z-10 absolute right-0 p-2 mt-2 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-indigo-500/50 focus:outline-none" >
                        <div class="mb-2 space-y-1">
                            <div @click="() => selectedColumnExport.length === props.export.columns.length ? selectedColumnExport = [] : selectedColumnExport = props.export.columns.map(column => column.value)"
                                class="flex items-center justify-end gap-x-1.5 whitespace-nowrap px-3 py-1 cursor-pointer select-none border-b border-gray-300"
                            >
                                {{ trans('Select all') }}
                                <FontAwesomeIcon v-if="selectedColumnExport.length === props.export.columns.length" icon='fas fa-check-square' class='' fixed-width aria-hidden='true' />
                                <FontAwesomeIcon v-else icon='fal fa-square' class='' fixed-width aria-hidden='true' />
                            </div>

                            <div v-for="column in props.export.columns" @click="onClickColumnExport(column.value)"
                                class="flex items-center justify-between rounded whitespace-nowrap px-3 py-1 cursor-pointer"
                                :class="selectedColumnExport.includes(column.value) ? 'bg-indigo-100' : 'hover:bg-gray-200'"
                            >
                                {{ column.label }}
                                <FontAwesomeIcon v-if="selectedColumnExport.includes(column.value)" icon='fas fa-check-square' class='' fixed-width aria-hidden='true' />
                                <FontAwesomeIcon v-else icon='fal fa-square' class='' fixed-width aria-hidden='true' />
                            </div>
                        </div>

                        <a
                            :href="route(props.export.route.name, {...props.export.route.parameters, columns: selectedColumnExport.join(',')})"
                            doxwnload
                            target="_blank"
                            xclass="bg-indigo-200/70 hover:bg-indigo-200 w-full leading-4 inline-flex justify-center items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max border border-gray-300 text-gray-700 rounded-md px-3 md:px-4 text-center py-[3px] md:py-[6px] text-sm"
                        >
                            <Button
                                icon="fas fa-file-export"
                                :loading="isExporting"
                                full
                                size="xs"
                            >
                                <template #label>
                                    <div>
                                        {{ trans('Export') }}
                                        <span v-if="isExporting">
                                            {{ progressExport }}/100%
                                        </span>
                                    </div>
                                </template>
                            </Button>
                            
                            <!-- <LoadingIcon v-if="isExporting" />
                            <div>
                                {{ trans('Export') }}
                                <span v-if="isExporting">
                                    {{ progressExport }}/100%
                                </span>
                            </div> -->
                        </a>
                    </MenuItems>
                </transition>
            </Menu>

            
        </template>

        <template #button-group-upload="{ action }">
            <Button
                @click="() => isModalUploadOpen = true"
                :style="'upload'"
                class="rounded-r-none text-sm border-none focus:ring-transparent focus:ring-offset-transparent focus:ring-0"
            />
        </template>
    </PageHeading>
    
    <TableLocations :data="data"  />



    <UploadExcel
        v-model="isModalUploadOpen"
        :title="upload_locations.title"
        :progressDescription="upload_locations.progressDescription"
        :upload_spreadsheet="upload_locations.upload_spreadsheet"
        :preview_template="upload_locations.preview_template"
    />

</template>
