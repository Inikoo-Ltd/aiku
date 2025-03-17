<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:31:26 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Location } from "@/types/location"
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import Tag from '@/Components/Tag.vue'
// import Multiselect from '@vueform/multiselect'
import MultiSelect from 'primevue/multiselect'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBox, faHandHoldingBox, faPallet, faPencil } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { debounce } from 'lodash-es'
import { Table as TableTS } from '@/types/Table'
library.add(faBox, faHandHoldingBox, faPallet, faPencil)

const props = defineProps<{
    data: TableTS,
    tagsList: Tag[]
    tab?: string
    tagRoute?: {}
}>()

interface Tag {
    id: number
    slug: string
    name: string
    type: string  // 'inventory'
}

function locationRoute(location: Location) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.infrastructure.dashboard':
        case 'grp.org.warehouses.show.infrastructure.locations.index':
            return route(
                'grp.org.warehouses.show.infrastructure.locations.show',
                [route().params['organisation'], route().params['warehouse'], location.slug])
        case 'grp.org.warehouse-areas.show':
        case 'grp.org.warehouse-areas.locations.index':
            return route(
                'grp.org.warehouse-areas.show.locations.show',
                [route().params['organisation'], route().params['warehouseArea'], location.slug])

        case 'grp.org.warehouses.show.infrastructure.warehouse_areas.show':
        case 'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index':
            return route(
                'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show',
                [route().params['organisation'], route().params['warehouse'], route().params['warehouseArea'], location.slug])
        case  'grp.overview.inventory.locations.index':
            return route(
                'grp.org.warehouses.show.infrastructure.locations.show',
                [location.organisation_slug, location.warehouse_slug, location.slug])
        default:
            return route(
                'grp.org.locations.show',
                [route().params['organisation'], location.slug])
    }
}

const tagsListTemp = ref<Tag[]>(props.tagsList)
const onEditLocation = ref(false)
const searchValueMultiselect = ref('')

// Section: Add new Tag
const isLoadingSaveTag = ref(false)
const addNewTag = async (tagName: string, currentTags: string[], idLocation: number) => {
    isLoadingSaveTag.value = true
    try {
        const response: any = await axios.post(route('grp.models.location.tag.store', idLocation),
            { name: tagName },
            {
                headers: { "Content-Type": "multipart/form-data" },
            }
        )
                
        currentTags.push(response.data.data.slug)
        updateTag(currentTags, idLocation)

        notify({
            title: trans('Success!'),
            text: trans('Adding new tag') + ` '${tagName}'`,
            type: "success"
        })

        searchValueMultiselect.value = ''

        tagsListTemp.value.push(response.data.data)
    } catch (error: any) {
        console.error(error.message)
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to create tag') + ` '${tagName}'`,
            type: "error"
        })
    } finally {
        isLoadingSaveTag.value = false
    }
}

// Section: update tag
const isSaveTag = ref(false)
const updateTag = debounce(async (tags: string[], idLocation: number) => {
    isSaveTag.value = true
    try {
        await axios.patch(route('grp.models.location.tag.attach', idLocation),
            { tags: tags },
        )

        // Refetch the data of Table to update the item.tags (v-model doesn't work)
        router.reload(
            {
                only: ['data']
            }
        )
    } catch (error: any) {
        console.error(error.message)
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to save tag'),
            type: "error"
        })
    } finally {
        isSaveTag.value = false
    }
}, 1200)

onMounted(() => {
    if (typeof window !== 'undefined') {
        document.addEventListener('keydown', (e) => e.keyCode == 27 ? onEditLocation.value = false : '')
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', () => false)
})

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(code)="{ item: location }">
            <Link :href="locationRoute(location)" class="primaryLink">
                {{ location.code }}
            </Link>
        </template>

        <!-- Column: Scope -->
        <template #cell(scope)="{ item: location }">
            <div class="flex">
                <div v-tooltip="location.allow_stocks ? 'Allow stock' : 'No stock'" class="px-1 py-0.5">
                    <FontAwesomeIcon icon='fal fa-box' fixed-width aria-hidden='true'
                        :class="[location.allow_stocks ? location.has_stock_slots ? 'text-green-500' : 'text-gray-400' : location.has_stock_slots ? 'text-red-500' : 'text-gray-400']"
                    />
                </div>
                <div v-tooltip="location.allow_dropshipping ? 'Allow dropshipping' : 'No dropshipping'" class="px-1 py-0.5">
                    <FontAwesomeIcon icon='fal fa-hand-holding-box' class='' fixed-width aria-hidden='true'
                        :class="[location.allow_dropshipping ? location.has_dropshipping_slots ? 'text-green-500' : 'text-gray-400' : location.has_dropshipping_slots ? 'text-red-500' : 'text-gray-400']"
                    />
                </div>
                <div v-tooltip="location.allow_fulfilment ? 'Allow fulfilment' : 'No fulfilment'" class="px-1 py-0.5">
                    <FontAwesomeIcon icon='fal fa-pallet' class='' fixed-width aria-hidden='true'
                        :class="[location.allow_fulfilment ? location.has_fulfilment ? 'text-green-500' : 'text-gray-400' : location.has_fulfilment ? 'text-red-500' : 'text-gray-400']"
                    />
                </div>
            </div>

            <!-- <pre>Stocks: {{ location }}</pre> -->

        </template>

        <!-- Column: Locations -->
        <template #cell(tags)="{ item, proxyItem }">
            <div class="group min-w-[200px] relative p-0">
                <div v-if="onEditLocation !== item.slug" class="flex gap-x-1 gap-y-1.5 items-center">
                    <template v-if="item.tags?.length">
                        <Tag v-for="tag in item.tags"
                            :label="tag"
                            :stringToColor="true"
                            size="sm"
                        />
                    </template>

                    <!-- Icon: pencil -->
                    <div class="opacity-50 group-hover:opacity-100 flex items-center px-1 py-1" @click="() => onEditLocation = item.slug">
                        <FontAwesomeIcon icon='fal fa-pencil' class='text-gray-400 text-lg cursor-pointer hover:text-gray-500' fixed-width aria-hidden='true' />
                    </div>
                </div>
                
                <div v-else>
                    <MultiSelect
                        :modelValue="proxyItem.tags"
                        @update:modelValue="(tags) => updateTag(tags, item.id)"
                        :options="tagsListTemp"
                        optionValue="slug"
                        optionLabel="name"
                        filter
                        placeholder="Select Tag"
                        filterPlaceholder="Search tag here"
                        :loading="isSaveTag || isLoadingSaveTag"
                        :maxSelectedLabels="3"
                        class="w-full"
                        @filter="(e) => searchValueMultiselect = e.value"
                    >
                        <template #emptyfilter>
                            <div class="">
                                {{ trans("No results for") }} <span class="font-semibold mr-2">{{ searchValueMultiselect }}</span>
                                <Button
                                    :label="`Create '${searchValueMultiselect}'`"
                                    @click="() => addNewTag(searchValueMultiselect, item.tags, item.id)"
                                    :loading="isLoadingSaveTag"
                                    :style="'secondary'"
                                />
                            </div>
                        </template>

                        <template #value="{ value }">
                            <div class="flex flex-wrap gap-x-1 gap-y-1.5">
                                <template v-if="value.length">
                                    <Tag v-for="val in value" :key="val" :label="val" stringToColor />
                                </template>
                                <div v-else class="text-gray-400 italic">
                                    {{ trans("No tags selected") }}
                                </div>
                            </div>
                        </template>
                    </MultiSelect>

                    <div class="mt-1 text-gray-400 italic text-xs">
                        Press Esc to finish edit or <span @click="() => onEditLocation = false" class="underline hover:text-gray-500 cursor-pointer">click here</span>.
                    </div>
                </div>
            </div>
        </template>
    </Table>
</template>
