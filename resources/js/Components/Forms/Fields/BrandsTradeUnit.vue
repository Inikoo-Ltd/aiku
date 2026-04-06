<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { set } from 'lodash-es'
import { routeType } from '@/types/route'

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        brand_routes: {
            index_brand: routeType
            attach_brand: routeType
            detach_brand: routeType
        }
    }
}>()

const isLoading = ref(false)

const onAttach = (brandId: number | null) => {
    if (!brandId) {
        onDetach()
        return
    }

    router[props.fieldData?.brand_routes.attach_brand.method || 'post'](
        route(props.fieldData?.brand_routes.attach_brand.name, props.fieldData?.brand_routes.attach_brand.parameters),
        { brand_id: brandId },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { isLoading.value = true },
            onSuccess: () => {
                notify({ title: trans('Success'), text: trans('Brand attached successfully'), type: 'success' })
            },
            onError: (error) => {
                notify({ title: trans('Something went wrong'), text: error.message, type: 'error' })
            },
            onFinish: () => { isLoading.value = false },
        }
    )
}

const onDetach = () => {
    if (!props.fieldData?.brand_routes.detach_brand?.name) return

    router[props.fieldData?.brand_routes.detach_brand.method || 'delete'](
        route(props.fieldData?.brand_routes.detach_brand.name, props.fieldData?.brand_routes.detach_brand.parameters),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { isLoading.value = true },
            onSuccess: () => {
                notify({ title: trans('Success'), text: trans('Brand detached successfully'), type: 'success' })
            },
            onError: (error) => {
                notify({ title: trans('Something went wrong'), text: error.message, type: 'error' })
            },
            onFinish: () => { isLoading.value = false },
        }
    )
}
</script>

<template>
    <div v-if="props.fieldData?.brand_routes?.index_brand" class="w-full max-w-md py-4">
        <PureMultiselectInfiniteScroll
            v-model="form[fieldName]"
            @update:modelValue="(e) => { set(form, [fieldName], e); onAttach(e) }"
            :fetchRoute="props.fieldData.brand_routes.index_brand"
            :placeholder="trans('Select brand')"
            valueProp="id"
            :isLoading="isLoading"
        >
            <template #singlelabel="{ value }">
                <div class="w-full text-left pl-4">
                    {{ value?.name || `#${value?.id}` }}
                </div>
            </template>
        </PureMultiselectInfiniteScroll>
    </div>
</template>
