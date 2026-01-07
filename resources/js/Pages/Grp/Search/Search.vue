<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup>

import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome';
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const applications = [
    {
        applicant: {
            name: 'Ricardo Cooper',
            email: 'ricardo.cooper@example.com',
            imageUrl:
                'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80',
        },
        date: '2020-01-07',
        dateFull: 'January 7, 2020',
        stage: 'Completed phone screening',
        href: '#',
    },
]

const searchResults = computed(() => usePage().props.searchResults)

const open = ref(true)
const query = ref('')

const searchInput = ref('');

let timeoutId;

function handleSearchInput() {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        console.log(searchInput.value);
        router.get(
            route('grp.search.run', {
                _query: {
                    q: searchInput.value
                }
            })
        );

    }, 200);
}

function handleKeyDown() {
    clearTimeout(timeoutId);
}

</script>

<template>

    <!--

    crear un input,

    añadir una de las tablas : https://tailwindui.com/components/application-ui/lists/stacked-lists | two column with avatar

    cuando busques en uno de ellos te devuelva los resultados por ejemplo del jbb

       en el input falta name and id
    -->
    <div class="mt-20 ml-14">
        <div>
            <label for="" class=" text-sm font-medium leading-6 text-gray-900">Search</label>
            <div class="mt-2">
                <input v-model="searchInput" @input="handleSearchInput" @keydown="handleKeyDown" type="text"  class="block w-auto  rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Type..." />
            </div>
        </div>

        <div v-for="item in searchResults.data" :key="item.id"  class="overflow-hidden bg-white mt-20 shadow sm:rounded-md max-w-2xl ">
            <ul role="list" class="divide-y divide-gray-200">
                <li>
                    <a :href="item.href" class="block hover:bg-gray-50">
                        <div class="flex items-center px-4 py-4 sm:px-6">
                            <div class="flex min-w-0 flex-1 items-center">
                                <div class="shrink-0">
                                    <img class="h-12 w-12 rounded-full" :src="item.imageUrl" alt="" />
                                </div>
                                <div class="min-w-0 flex-1 px-4 md:grid md:grid-cols-2 md:gap-4">
                                    <div>
                                        <p class="truncate text-sm font-medium text-indigo-600">{{ item.name }}</p>
                                        <p class="mt-2 flex items-center text-sm text-gray-500">
                                            <FontAwesomeIcon class="mr-1.5 h-5 w-5 shrink-0 text-gray-400" icon="far fa-chevron-right" aria-hidden="true" />
                                            <span class="truncate">{{ item.email }}</span>
                                        </p>
                                    </div>
                                    <div class="hidden md:block">
                                        <div>
                                            <p class="text-sm text-gray-900">
                                                Applied on
                                                {{ ' ' }}
                                                <time :datetime="item.date">{{ item.dateFull }}</time>
                                            </p>
                                            <p class="mt-2 flex items-center text-sm text-gray-500">
                                                <FontAwesomeIcon class="mr-1.5 h-5 w-5 shrink-0 text-green-400" icon="far fa-chevron-right" aria-hidden="true" />
                                                {{ item.stage }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <FontAwesomeIcon class="h-5 w-5 text-gray-400" icon="far fa-chevron-right" aria-hidden="true" />
                            </div>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </div>


</template>

