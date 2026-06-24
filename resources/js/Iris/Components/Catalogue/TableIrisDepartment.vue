<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "../Tables/Table.vue";
import Icon from "@/Components/Icon.vue";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Tag from "@/Components/Tag.vue";
import Image from "@common/Components/Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faExternalLink } from "@far";

library.add(faSeedling);

defineProps<{
    data: {}
    tab?: string
}>();
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image
                    :src="item.image_thumbnail ?? item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="w-6 aspect-square rounded-full overflow-hidden shadow"
                />
            </div>
        </template>
        <template #cell(state)="{ item: department }">
            <Tag :label="department.state.label" v-tooltip="department.state.label">
                <template #label>
                    <Icon :data="department.state" /> <span :class="department.state.class">{{ department.state.label
                        }}</span>
                </template>
            </Tag>
        </template>
        <template #cell(code)="{ item: department }">
            <Link
                :href="route('iris.catalogue.department.show', { department: department.slug })"
                class="primaryLink inline-block"
            >
                {{ department.code }}
            </Link>
        </template>
        <template #cell(number_current_families)="{ item: department }">
            {{ department["number_current_families"] }}
        </template>
        <template #cell(number_current_sub_departments)="{ item: department }">
            {{ department["number_current_sub_departments"] }}
        </template>
        <template #cell(number_current_collections)="{ item: department }">
            {{ department["number_current_collections"] }}
        </template>
        <template #cell(number_current_products)="{ item: department }">
            {{ department["number_current_products"] }}
        </template>
        <template #cell(public_url)="{ item: department }">
            <div class="flex justify-center">
                <a v-if="department.public_url" :href="department.public_url" target="_blank">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
        </template>
    </Table>
</template>
