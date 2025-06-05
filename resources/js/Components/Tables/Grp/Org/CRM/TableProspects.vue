<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link, router} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import { Prospect } from "@/types/prospect";
import Multiselect from '@vueform/multiselect'
import Tag from '@/Components/Tag.vue';
import { ref } from 'vue'
import Icon from '@/Components/Icon.vue';

import { routeType } from '@/types/route';
import { notify } from '@kyvg/vue3-notification';

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faThumbsDown, faChair, faLaugh } from '@fal';
import { faCheck } from '@far';
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from 'laravel-vue-i18n'
library.add(faThumbsDown, faChair, faLaugh, faCheck)

interface tag {
    id: number
    slug: string
    name: string
    type: boolean
}

const props = defineProps<{
    data: {
        data : object,
        tagRoute : {
            update : routeType
            store : routeType
        }
        tagsList : {
            data : Array<tag>
        }
    },
    tab : String
}>()


const onCreateTag = (option: tag, event: any, prospect: Prospect): Promise<tag | boolean> => {
    return new Promise((resolve, reject) => {
        router.post(
            route(props.data.tagRoute.store.name, { prospect: prospect.id }),
            option,
            {
                preserveScroll: true,
                onSuccess: () => {
                    resolve(option); // Resolve the Promise with the option
                },
                onError: () => {
                    notify({
                        title: "Failed to add new Tag",
                        text: "Failed to update the Prospect",
                        type: "error",
                    });
                    reject(false); // Reject the Promise with false
                },
            }
        );
    });
};



const onUpdateTag = (idTag : Number, prospect : Prospect) =>{
    router.patch(
        route(props.data.tagRoute.update.name,{ prospect : prospect.id }),
        { tags: idTag },
        {
            preserveScroll: true,
            onSuccess : ()=> {}, 
            onError : ()=>{
                notify({
                    title: "Failed",
                    text: "failed to update the Prospect",
                    type: "error"
                })
                return false 
            }
        }
    )
}


const tagsListTemp: Ref<tag[]> = ref([])
const maxId = ref(Math.max(...tagsListTemp.value.map(item => item.id)))

function prospectRoute(prospect: Prospect) {
    switch (route().current()) {
        case 'grp.org.shops.show.crm.prospects.index':
            return route(
                'grp.org.shops.show.crm.prospects.show',
                [route().params["organisation"], route().params['shop'], prospect.slug]);
        default:
            return route(
                'prospects.show',
                [prospect.slug]);
    }
}

</script>

<template>
    <Table :resource="data.data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: prospect }">
            <Icon :data="prospect.state_icon"></Icon>
        </template>
        
        <template #cell(name)="{ item: prospect }">
            <Link :href="prospectRoute(prospect)" class="primaryLink">
                {{ prospect['name'] }}
            </Link>
        </template>

        <!-- Column: Opt in -->
        <template #cell(is_opt_in)="{ item: prospect }">
            <div class="text-center">
                <FontAwesomeIcon v-if="prospect.is_opt_in" v-tooltip="trans('Opt-in to newsletter')" icon="far fa-check" class="text-green-500" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(tags)="{ item: prospect }">
            <div class="min-w-[200px]">
                <Multiselect 
                    v-model="prospect.tags"
                    :onCreate="(value : tag, event : any)=>onCreateTag(value,event,prospect)"
                    :key="prospect.id"
                    mode="tags"
                    placeholder="Select the tag"
                    valueProp="name"
                    trackBy="name"
                    label="name"
                    @change="(idTag : Number) => onUpdateTag(idTag, prospect)"
                    :close-on-select="false"
                    :searchable="true"
                    :create-option="true"
                    :caret="false"
                    :options="data.tagsList.data"
                    noResultsText="No one left. Type to add new one."
                    appendNewTag
                >
                    <template #tag="{ option, handleTagRemove, disabled }: {option: tag, handleTagRemove: Function, disabled: boolean}">
                        <div class="px-0.5 py-[3px]">
                            <Tag
                                :theme="option.id ?? maxId"
                                :label="option.name"
                                :closeButton="true"
                                :stringToColor="true"
                                size="sm"
                                @onClose="(event) => handleTagRemove(option, event)"
                            />
                        </div>
                    </template>
                </Multiselect>
            </div>
        </template>
    </Table>
</template>


