<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faEnvelope, faStore } from '@fal'
import Carousel from 'primevue/carousel'

library.add(faEnvelope, faStore);

interface Template {
    id: number;
    name?: string;
    subject?: string;
    shop_name?: string;
    compiled_layout: string;
    created_at?: string;
}

interface Props {
    mailshotId: string;
    shopId: string;
    organisationSlug: string;
    shopSlug: string;
    ownShopTemplates: { templates: Template[], shop_name: string };
    otherShopTemplates: { templates: Template[] };
}

const props = defineProps<Props>();

const carouselOptions = computed(() => ({
    responsive: [
        {
            breakpoint: '1024px',
            numVisible: 3,
            numScroll: 1
        },
        {
            breakpoint: '768px',
            numVisible: 2,
            numScroll: 1
        },
        {
            breakpoint: '560px',
            numVisible: 1,
            numScroll: 1
        }
    ],
    numVisible: 3,
    numScroll: 1,
    circular: false,
    autoplayInterval: 0,
    showNavigators: true,
    showIndicators: false
}));

const hasOwnTemplates = computed(() => props.ownShopTemplates?.templates?.length > 0);
const hasOtherTemplates = computed(() => props.otherShopTemplates?.templates?.length > 0);
const hasAnyTemplates = computed(() => hasOwnTemplates.value || hasOtherTemplates.value);

const getTemplatePreview = (template: Template): string => {
    // Return the full HTML content
    return template.compiled_layout;
};

const formatDate = (dateString?: string): string => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <div class="space-y-8">
        <!-- Own Shop Templates -->
        <div v-if="hasOwnTemplates">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <FontAwesomeIcon :icon="faStore" class="mr-2 text-blue-600" />
                    {{ trans('Your Templates') }}
                    <span class="ml-2 text-sm text-gray-500">({{ props.ownShopTemplates.shop_name }})</span>
                </h3>
            </div>

            <Carousel :value="props.ownShopTemplates.templates" :options="carouselOptions" class="mb-8">
                <template #item="slotProps">
                    <div class="mx-2">
                        <div
                            class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow h-full">
                            <div class="p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900 truncate">{{ slotProps.data.name ||
                                            slotProps.data.subject }}</h4>
                                        <p v-if="slotProps.data.created_at" class="text-sm text-gray-500">{{
                                            formatDate(slotProps.data.created_at) }}</p>
                                    </div>
                                    <FontAwesomeIcon :icon="faEnvelope" class="text-gray-400 ml-2" />
                                </div>

                                <div class="mb-4">
                                    <div
                                        class="w-full aspect-[2/5] bg-gray-50 border border-gray-200 rounded overflow-auto">
                                        <div class="p-2" v-html="getTemplatePreview(slotProps.data)"></div>
                                    </div>
                                </div>

                                <Link :href="route('grp.helpers.redirect_mailshot_template_workshop', {
                                    organisation: props.organisationSlug,
                                    shop: props.shopSlug,
                                    mailshot: props.mailshotId,
                                    template: slotProps.data.id
                                })" class="block w-full">
                                    <Button :label="trans('Use Template')" type="primary" size="sm" class="w-full" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </Carousel>
        </div>

        <!-- Other Shop Templates -->
        <div v-if="hasOtherTemplates">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <FontAwesomeIcon :icon="faStore" class="mr-2 text-green-600" />
                    {{ trans('Templates from Other Shops') }}
                </h3>
            </div>

            <Carousel :value="props.otherShopTemplates.templates" :options="carouselOptions" class="mb-8">
                <template #item="slotProps">
                    <div class="mx-2">
                        <div
                            class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow h-full">
                            <div class="p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900 truncate">{{ slotProps.data.name ||
                                            slotProps.data.subject }}</h4>
                                        <p class="text-sm text-gray-500">
                                            {{ slotProps.data.shop_name }}{{ slotProps.data.created_at ? ' • ' +
                                                formatDate(slotProps.data.created_at) : '' }}
                                        </p>
                                    </div>
                                    <FontAwesomeIcon :icon="faEnvelope" class="text-gray-400 ml-2" />
                                </div>

                                <div class="mb-4">
                                    <div
                                        class="w-full aspect-[2/5] bg-gray-50 border border-gray-200 rounded overflow-auto">
                                        <div class="p-2" v-html="getTemplatePreview(slotProps.data)"></div>
                                    </div>
                                </div>

                                <Link :href="route('grp.helpers.redirect_mailshot_template_workshop', {
                                    organisation: props.organisationSlug,
                                    shop: props.shopSlug,
                                    mailshot: props.mailshotId,
                                    template: slotProps.data.id
                                })" class="block w-full">
                                    <Button :label="trans('Use Template')" type="secondary" size="sm" class="w-full" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </Carousel>
        </div>

        <!-- No Templates Message -->
        <div v-if="!hasAnyTemplates" class="text-center py-8">
            <FontAwesomeIcon :icon="faEnvelope" class="text-4xl text-gray-300 mb-4" />
            <p class="text-gray-500">{{ trans('No email templates with generated HTML found.') }}</p>
            <p class="text-sm text-gray-400 mt-2">
                {{ trans('Create some email templates first to see them here.') }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
