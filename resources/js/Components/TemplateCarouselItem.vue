<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faEnvelope } from '@fal'
import { routeType } from "@/types/route";

library.add(faEnvelope);

interface Template {
    id: number;
    slug: string;
    name?: string;
    subject?: string;
    shop_name?: string;
    compiled_layout: string;
    created_at?: string;
}

interface Props {
    template: Template;
    buttonType: 'primary' | 'secondary';
    showShopName: boolean;
    workshopRoute?: routeType;
}

const props = defineProps<Props>();

const getTemplatePreview = (template: Template): string => {
    // Return the full HTML content with 50% zoom and disabled links
    return `<div style="transform: scale(0.45); transform-origin: top left; width: 222%; height: 100%; overflow: hidden;">
        ${template.compiled_layout}
    </div>`;
};

const formatDate = (dateString?: string): string => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <div class="mx-2">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow h-full">
            <div class="p-4">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 truncate">{{ template.name || template.subject }}</h4>
                        <p class="text-sm text-gray-500">
                            <span v-if="showShopName && template.shop_name">{{ template.shop_name }}</span>
                            <span v-if="showShopName && template.shop_name && template.created_at"> &bull; </span>
                            <span v-if="template.created_at">{{ formatDate(template.created_at) }}</span>
                        </p>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="w-full h-[250px] aspect-[3/4] bg-gray-50 border border-gray-200 rounded overflow-auto">
                        <div class="p-2" v-html="getTemplatePreview(template)"></div>
                    </div>
                </div>

                <Link v-if="workshopRoute?.name" :href="route(workshopRoute.name, {
                    ...workshopRoute.parameters,
                    template: template.slug
                })" class="block w-full">
                    <Button :label="trans('Use Template')" :type="buttonType" size="sm" class="w-full" />
                </Link>
            </div>
        </div>
    </div>
</template>
