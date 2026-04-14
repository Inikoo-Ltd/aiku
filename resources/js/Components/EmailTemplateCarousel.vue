<script setup lang="ts">
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faStore } from '@fal'
import Carousel from 'primevue/carousel'
import TemplateCarouselItem from './TemplateCarouselItem.vue'

library.add(faStore);

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
    otherShopTemplates: { templates: Template[] } | { data: Template[], current_page: number, last_page: number, per_page: number, total: number };
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

const otherShopTemplatesData = computed(() => {
    if (!props.otherShopTemplates) return [];
    // Handle both old format (templates array) and new paginated format (data array)
    return Array.isArray(props.otherShopTemplates)
        ? props.otherShopTemplates
        : (props.otherShopTemplates.data || []);
});

const hasOtherTemplates = computed(() => otherShopTemplatesData.value.length > 0);
const hasAnyTemplates = computed(() => hasOwnTemplates.value || hasOtherTemplates.value);

const pagination = computed(() => {
    if (!props.otherShopTemplates || Array.isArray(props.otherShopTemplates)) {
        return null;
    }
    return {
        currentPage: props.otherShopTemplates.current_page,
        lastPage: props.otherShopTemplates.last_page,
        perPage: props.otherShopTemplates.per_page,
        total: props.otherShopTemplates.total
    };
});

const formatDate = (dateString?: string): string => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString();
};

const emit = defineEmits<{
    loadOtherShopTemplates: [page: number];
}>();

const loadPage = (page: number) => {
    if (page >= 1 && pagination.value && page <= pagination.value.lastPage) {
        emit('loadOtherShopTemplates', page);
    }
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

            <Carousel :value="props.ownShopTemplates.templates" :numVisible="4" :options="carouselOptions" class="mb-8">
                <template #item="slotProps">
                    <TemplateCarouselItem :template="slotProps.data" :organisation-slug="props.organisationSlug"
                        :shop-slug="props.shopSlug" :mailshot-id="props.mailshotId" button-type="primary"
                        :show-shop-name="false" :show-envelope-icon="true" />
                </template>
            </Carousel>
        </div>

        <!-- Other Shop Templates -->
        <div v-if="hasOtherTemplates">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <FontAwesomeIcon :icon="faStore" class="mr-2 text-green-600" />
                    {{ trans('Templates from Other Shops') }}
                    <span v-if="pagination" class="ml-2 text-sm text-gray-500">
                        ({{ pagination.total }} total)
                    </span>
                </h3>
            </div>

            <Carousel :value="otherShopTemplatesData" :numVisible="4" :options="carouselOptions" class="mb-8">
                <template #item="slotProps">
                    <TemplateCarouselItem :template="slotProps.data" :organisation-slug="props.organisationSlug"
                        :shop-slug="props.shopSlug" :mailshot-id="props.mailshotId" button-type="secondary"
                        :show-shop-name="true" :show-envelope-icon="false" />
                </template>

                <template #footer v-if="pagination">
                    <div class="flex justify-center items-center space-x-4 py-3 border-t border-gray-200">
                        <button @click="loadPage(pagination.currentPage - 1)" :disabled="pagination.currentPage <= 1"
                            class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ trans('Previous') }}
                        </button>

                        <span class="text-sm text-gray-600">
                            {{ trans('Page :currentPage of :lastPage', {
                                currentPage: pagination.currentPage,
                                lastPage: pagination.lastPage
                            }) }}
                        </span>

                        <button @click="loadPage(pagination.currentPage + 1)"
                            :disabled="pagination.currentPage >= pagination.lastPage"
                            class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ trans('Next') }}
                        </button>
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
