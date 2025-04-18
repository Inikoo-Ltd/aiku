<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    shops: {
        id: number
        name: string
    }[]
    routes: {
        get_started: routeType
    }
}>()

const isModalGetStarted = ref(false)
const onClickGetStarted = (id: number) => {
    isModalGetStarted.value = false

    router[props.routes.get_started.method || 'post'](route(props.routes.get_started.name, props.routes.get_started.parameters), {
        shop: id
    }, {
        headers: {
            Authorization: `Bearer ${window.sessionToken}`
        },
        preserveState: true,
        onSuccess: () => {
            router.reload()
        },
        onError: (error) => {
            console.error('error get started: ', error)
        }
    })
}
</script>

<template>
    <div class="relative isolate overflow-hidden px-6 py-8 text-center sm:rounded-3xl sm:px-12">
        <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
            {{ trans(`Let's get started.`) }}
        </h2>

        <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-500">
            It's looks like this is the first time you integrate Shopify, let's have a look what you can do.
        </p>

        <div class="mt-10 flex items-center justify-center gap-x-6">
            <div v-for="shop in props.shops">
                <div class="flex flex-col p-4 border-gray-300 border rounded">
                    <img class="w-72 h-48 object-cover text-center" v-if="shop.name === 'AW Fulfilment'" src="https://i.ibb.co.com/CxTbCRf/undraw-factory-4d61.png" :alt="`${shop.name}`">
                    <img class="w-72 h-48 object-cover text-center" v-else src="https://i.ibb.co.com/9k4B20qk/undraw-financial-data-r0vs.png" :alt="`${shop.name}`">
                    <Button @click="() => onClickGetStarted(shop.id)" type="tertiary" size="l" :label="`Setup ${shop.name}`" />
                </div>
            </div>
        </div>
    </div>
</template>
