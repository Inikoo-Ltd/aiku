<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

const props = defineProps<{
    data?: {}
    tab?: string
    state:any
    key:any
    tableKey?: string
}>()

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(delta)="{ item: item }">
            <div v-if="item.delta > 0" class="text-green-500">
                {{ "+" + Math.floor(item.delta) }}
            </div>
            <div v-else-if="item.delta < 0" class="text-red-500">
                {{ Math.floor(item.delta) }}
            </div>
            <div v-else>
                {{ Math.floor(item.delta) }}
            </div>
        </template>
        <template #cell(description)="{ item }">
            <!-- edit type : {{ item.edit_type }} -->
            <div v-if="item.description?.model || item.description?.title || item.description?.after_title">
            <FontAwesomeIcon :icon="item.description.icon" fixed-width aria-hidden="true" class="pr-2" />
                <span v-if="item.description?.model">{{ item.description.model }}:</span>
                <Link
                    v-if="item.description?.title && item.description.route?.name?.trim()"
                    :href="route(item.description.route.name.trim(), item.description.route?.parameters)"
                    class="primaryLink">
                    {{ item.description.title }}
                </Link>
                <span v-else>&nbsp;{{ item.description.title }}</span>

                <div v-if="item.description.after_title" class="text-gray-400 italic text-xs">({{ item.description.after_title }})</div>
            </div>

            <div v-else>

            </div>
        </template>
    </Table>
</template>
