<script setup lang="ts">
import { routeType } from '@/types/route';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faInfoCircle } from "@fas";
import Message from "primevue/message";
import { Link } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faAlbumCollection } from "@fal";
import TranslationBox from '@/Components/TranslationBox.vue';
import ProductCategoryCard from '@/Components/ProductCategoryCard.vue';
import { trans } from 'laravel-vue-i18n';

library.add(faAlbumCollection);

const props = defineProps<{
    data: {
        translation_box: {
            title: string
            save_route: routeType
        }
        family: {
            data: {},
        },
        routeList: {
            collectionRoute: routeType
        },
        routes: {
            detach_family: routeType
        }
    }
}>()

console.log(props)
</script>

<template>
    <div class="pb-8 m-5">
         <Message v-if="data.family?.data.url_master" severity="success" closable>
            <template #icon>
                <FontAwesomeIcon :icon="faInfoCircle" />
            </template>
            <span class="ml-2">
                {{ trans("Right now you follow") }}
                <Link :href="route(data.family.data.url_master.name, data.family.data.url_master.parameters)"
                    class="underline font-bold">
                {{ trans("the master data") }}
                </Link>
            </span>
        </Message>


        <div class="px-5 grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 mb-4 ">
            <ProductCategoryCard :data="data.family.data" />
        </div>



     <!--    <TranslationBox :master="data.family.data" :needTranslation="data.family.data" v-bind="data.translation_box" /> -->
    </div>
</template>
