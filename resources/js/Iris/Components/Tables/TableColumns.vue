<script setup>
import { trans } from 'laravel-vue-i18n'
import ButtonWithDropdown from "./ButtonWithDropdown.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faYinYang, faUserCircle, } from '@fal'
import { faEye } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faYinYang, faUserCircle, faEye)

const props = defineProps({
    columns: {
        type: Object,
        required: true,
    },

    hasHiddenColumns: {
        type: Boolean,
        required: true,
    },

    onChange: {
        type: Function,
        required: true,
    },
});
</script>

<template>
    <ButtonWithDropdown placement="bottom-end" dusk="columns-dropdown" :active="hasHiddenColumns" id="filter-colums">
        <!-- Buttons beside of Search Table (Filter column, etc.) -->
        <template #button>
            <FontAwesomeIcon icon="fas fa-eye" aria-hidden="true"
                :class="[hasHiddenColumns ? 'text-green-400' : 'text-gray-400', 'h-5 w-5']" />
        </template>

        <!-- The popup -->
        <div role="menu" aria-orientation="horizontal" aria-labelledby="toggle-columns-menu" class="min-w-max">
            <div class="px-2">
                <ul class="divide-y divide-gray-200">
                    <li v-for="(column, key) in props.columns" v-show="column.can_be_hidden" :key="key"
                        class="py-2 flex items-center justify-between">
                        <p class="text-sm text-gray-800">
                            {{ typeof column.label == 'string' ? trans(column.label) : '' }}
                            <FontAwesomeIcon v-if="(typeof column.label != 'string')" class="text-gray-700"
                                :icon="column.label" aria-hidden="true" />
                        </p>

                        <!-- Switch Toggle -->
                        <button type="button"
                            :id="column.label"
                            class="ml-4 relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-1 focus:ring-offset-2 focus:ring-gray-600"
                            :class="[column.hidden ? 'bg-gray-200' : 'bg-gray-700']"
                            :aria-pressed="!column.hidden" :aria-labelledby="`toggle-column-${column.key}`"
                            :aria-describedby="`toggle-column-${column.key}`" :dusk="`toggle-column-${column.key}`"
                            @click.prevent="onChange(column.key, column.hidden)">
                            <span class="sr-only">Column status</span>
                            <span aria-hidden="true" :class="{
                                'translate-x-5': !column.hidden,
                                'translate-x-0': column.hidden,
                            }" class="inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200" />
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </ButtonWithDropdown>
</template>
