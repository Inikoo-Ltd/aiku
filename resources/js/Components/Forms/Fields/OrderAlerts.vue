<script setup lang='ts'>
import { computed } from 'vue'
import { Switch } from '@headlessui/vue'
import SoundPicker from '@/Components/Forms/Fields/SoundPicker.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheck } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { ORDER_ALERT_PUNCH, ORDER_ALERT_SOUNDS, orderAlertSoundLabels, playOrderAlertSound, type OrderAlertSound } from '@/Composables/useNotificationSound'

type OrderAlertsValue = {
    shops: number[]
    types: Record<string, { enabled: boolean, sound: OrderAlertSound, muted?: boolean }>
    popup: { show: boolean }
}

type ShopOption = { id: number, code: string, label: string, type: string }

const props = defineProps<{
    form: any
    fieldName: string
    options?: {
        shops?: ShopOption[]
        types?: { value: string, label: string }[]
    }
    fieldData: {}
}>()

library.add(faCheck)

const labels = orderAlertSoundLabels()

const emojis: Record<OrderAlertSound, string> = {
    till: '💰',
    yeehaw: '🤠',
    bell: '🔔',
    coins: '🪙',
    funny: '🤡',
    oh_yeah: '😏',
    silent: '🔇',
}

const isMuted = (type: string) => !!value.value.types[type]?.muted

const hints: Record<string, string> = {
    ecom_small: ctrans("The shop's smallest quarter of orders"),
    ecom_normal: ctrans('Everything in between'),
    ecom_big: ctrans("The shop's top 10% of orders"),
    dropshipping_unpaid: ctrans('Waiting for the customer to pay'),
    dropshipping_first_channel_order: ctrans("A customer's newly connected store sends its first order"),
}

const value = computed<OrderAlertsValue>(() => props.form[props.fieldName])

const blocks = computed(() => {
    const types = props.options?.types ?? []
    const shops = [...(props.options?.shops ?? [])].sort((a, b) => a.code.localeCompare(b.code, undefined, { sensitivity: 'base' }))
    return [
        {
            label: ctrans('Ecom'),
            types: types.filter((type) => type.value.startsWith('ecom_')),
            shops: shops.filter((shop) => shop.type !== 'dropshipping'),
        },
        {
            label: ctrans('Dropshipping'),
            types: types.filter((type) => type.value.startsWith('dropshipping_')),
            shops: shops.filter((shop) => shop.type === 'dropshipping'),
        },
    ].filter((block) => block.shops.length)
})

const update = (changes: Partial<OrderAlertsValue>) => {
    props.form[props.fieldName] = { ...value.value, ...changes }
}

const setType = (type: string, changes: Partial<{ enabled: boolean, sound: OrderAlertSound, muted: boolean }>) => {
    update({ types: { ...value.value.types, [type]: { ...value.value.types[type], ...changes } } })
}

const isFollowed = (shopId: number) => value.value.shops.includes(shopId)

const toggleShop = (shopId: number) => {
    update({ shops: isFollowed(shopId) ? value.value.shops.filter((id) => id !== shopId) : [...value.value.shops, shopId] })
}

const setGroup = (shops: ShopOption[], followed: boolean) => {
    const ids = shops.map((shop) => shop.id)
    const others = value.value.shops.filter((id) => !ids.includes(id))
    update({ shops: followed ? [...others, ...ids] : others })
}

const followedIn = (shops: ShopOption[]) => shops.filter((shop) => isFollowed(shop.id)).length

const preview = (type: string) => playOrderAlertSound(value.value.types[type]?.sound ?? 'till', ORDER_ALERT_PUNCH[type] ?? 2)
</script>

<template>
    <div class="w-full space-y-6 text-sm">
        <section v-for="block in blocks" :key="block.label" class="rounded-lg border border-gray-200">
            <header class="flex items-center gap-x-3 rounded-t-lg border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                <span class="font-semibold text-gray-900">{{ block.label }}</span>
                <span class="text-xs tabular-nums text-gray-400">{{ ctrans(':count of :total shops followed', { count: followedIn(block.shops), total: block.shops.length }) }}</span>
            </header>

            <div v-for="type in block.types" :key="type.value" class="flex items-center gap-x-4 border-b border-gray-100 px-4 py-3">
                <Switch
                    :modelValue="!!value.types[type.value]?.enabled"
                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    :class="value.types[type.value]?.enabled ? 'bg-indigo-500' : 'bg-gray-200'"
                    @update:modelValue="(enabled: boolean) => setType(type.value, { enabled })">
                    <span class="sr-only">{{ type.label }}</span>
                    <span aria-hidden="true" class="pointer-events-none h-4 w-4 transform rounded-full bg-white shadow transition"
                        :class="value.types[type.value]?.enabled ? 'translate-x-4' : 'translate-x-0'" />
                </Switch>

                <div class="min-w-0 flex-1">
                    <div class="font-medium" :class="value.types[type.value]?.enabled ? 'text-gray-900' : 'text-gray-500'">{{ type.label }}</div>
                    <div class="text-xs text-gray-400">{{ hints[type.value] }}</div>
                </div>

                <SoundPicker
                    :sound="value.types[type.value]?.sound ?? 'till'"
                    :muted="isMuted(type.value)"
                    :sounds="ORDER_ALERT_SOUNDS"
                    :labels="labels"
                    :emojis="emojis"
                    :label="type.label"
                    :dimmed="!value.types[type.value]?.enabled"
                    @update:sound="(sound) => setType(type.value, { sound: sound as OrderAlertSound })"
                    @update:muted="(muted) => setType(type.value, { muted })"
                    @play="preview(type.value)" />
            </div>

            <div class="px-4 py-3">
                <div class="mb-2 flex items-center gap-x-3 text-xs">
                    <span class="font-medium uppercase tracking-wide text-gray-500">{{ ctrans('Shops') }}</span>
                    <span class="ml-auto flex gap-x-3">
                        <button type="button" class="text-indigo-600 hover:underline" @click="setGroup(block.shops, true)">{{ ctrans('All') }}</button>
                        <button type="button" class="text-indigo-600 hover:underline" @click="setGroup(block.shops, false)">{{ ctrans('None') }}</button>
                    </span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="shop in block.shops"
                        :key="shop.id"
                        type="button"
                        :aria-pressed="isFollowed(shop.id)"
                        v-tooltip="shop.label"
                        class="inline-flex items-center gap-x-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors"
                        :class="isFollowed(shop.id) ? 'border-green-300 bg-green-50 text-gray-800 hover:bg-green-100' : 'border-gray-200 bg-white text-gray-400 hover:border-gray-300 hover:text-gray-600'"
                        @click="toggleShop(shop.id)">
                        <FontAwesomeIcon v-if="isFollowed(shop.id)" icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                        <span v-else class="h-1.5 w-1.5 rounded-full bg-gray-300" aria-hidden="true" />
                        {{ shop.code }}
                    </button>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <div class="flex items-center gap-x-3">
                <Switch
                    :modelValue="value.popup.show"
                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    :class="value.popup.show ? 'bg-indigo-500' : 'bg-gray-200'"
                    @update:modelValue="(show: boolean) => update({ popup: { ...value.popup, show } })">
                    <span class="sr-only">{{ ctrans('Show pop-up') }}</span>
                    <span aria-hidden="true" class="pointer-events-none h-4 w-4 transform rounded-full bg-white shadow transition"
                        :class="value.popup.show ? 'translate-x-4' : 'translate-x-0'" />
                </Switch>
                <span class="text-gray-700">{{ ctrans('Show a pop-up with the order') }}</span>
            </div>
        </div>
    </div>
</template>
