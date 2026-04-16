<script setup lang="ts">
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faStore } from '@fal'
import Carousel from 'primevue/carousel'
import ProgressSpinner from 'primevue/progressspinner'
import axios from 'axios'
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

const isLoading = ref(false);
const localOtherShopTemplates = ref<Template[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);

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
    if (localOtherShopTemplates.value.length > 0) {
        return localOtherShopTemplates.value;
    }
    if (!props.otherShopTemplates) return [];
    // Handle both old format (templates array) and new paginated format (data array)
    return Array.isArray(props.otherShopTemplates)
        ? props.otherShopTemplates
        : (props.otherShopTemplates.data || []);
});

const hasOtherTemplates = computed(() => otherShopTemplatesData.value.length > 0);
const hasAnyTemplates = computed(() => hasOwnTemplates.value || hasOtherTemplates.value);

const pagination = computed(() => {
    if (localOtherShopTemplates.value.length > 0) {
        return {
            currentPage: currentPage.value,
            lastPage: lastPage.value,
            perPage: 4,
            total: lastPage.value * 4
        };
    }
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

const handlePageUpdate = (event: number) => {

    console.log(event)
    // const pageNumber = event + 1;
    // const numVisible = carouselOptions.value.numVisible;
    // const neededItems = numVisible * pageNumber;

    // if (neededItems > 4 && !isLoading.value) {
    //     const nextPage = Math.ceil(neededItems / 4);
    //     if (nextPage > currentPage.value && nextPage <= lastPage.value) {
    fetchOtherShopTemplates(event + 1);
    //     }
    // }
};

const fetchOtherShopTemplates = async (page: number) => {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
        const response = await axios.get(`/json/organisation/${props.organisationSlug}/shop/${props.shopSlug}/email-templates/other-shops`, {
            params: {
                page: page,
                perPage: 4
            }
        });

        const data = response.data;
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;

        // Append new templates to local data
        localOtherShopTemplates.value = [...localOtherShopTemplates.value, ...data.data];
    } catch (error) {
        console.error('Error fetching other shop templates:', error);
    } finally {
        isLoading.value = false;
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

            <div class="relative">
                <Carousel :value="otherShopTemplatesData" :numVisible="4" :numScroll="4" :circular="true"
                    :options="carouselOptions" @update:page="handlePageUpdate" class="mb-8"
                    :class="{ 'opacity-50 pointer-events-none': isLoading }">
                    <template #item="slotProps">
                        <TemplateCarouselItem :template="slotProps.data" :organisation-slug="props.organisationSlug"
                            :shop-slug="props.shopSlug" :mailshot-id="props.mailshotId" button-type="secondary"
                            :show-shop-name="true" :show-envelope-icon="false" />
                    </template>
                </Carousel>

                <!-- Loading Overlay -->
                <div v-if="isLoading"
                    class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 z-10">
                    <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                </div>
            </div>
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
