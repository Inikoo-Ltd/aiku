<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { trans } from "laravel-vue-i18n"
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import Modal from "@/Components/Utils/Modal.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import Image from '@/Components/Image.vue'
import axios from 'axios'

const model = defineModel()
const emit = defineEmits(['update:modelValue'])

const modelModalBlocklist = ref(false)
const webBlockTypes = ref<Array<any>>([])

const onPickBlock = (block: any) => {
	modelModalBlocklist.value = false
	emit('update:modelValue', block)
}

const getWebBlockTypes = async () => {
	try {
		const { data } = await axios.get(route('grp.json.web-block-types.index'))
		webBlockTypes.value = data
	} catch (error) {
		notify({
			title: trans("Something went wrong"),
			text: trans("Failed to load block types"),
			type: "error"
		})
	}
}

onMounted(() => {
	getWebBlockTypes()
})
</script>

<template>
	<div class="group hover:bg-gray-100 relative border border-gray-400 border-dashed overflow-hidden rounded-md text-center cursor-pointer" @click="modelModalBlocklist = true">

        <div v-if="!model" class="text-sm">
            <div class="py-3">
                <p>{{ trans("Pick Block") }}</p>
            </div>
        </div>

        <div v-else class="h-32 relative flex justify-center items-center">
            <Image :src="model?.screenshot" class="w-auto h-fit" />
            <div class="absolute hover:bg-black/50 z-10 inset-0 flex items-center justify-center text-white text-sm opacity-0 group-hover:opacity-100">
                {{ trans("Pick Block") }}
            </div>
        </div>
    </div>
		<Modal :isOpen="modelModalBlocklist" @onClose="modelModalBlocklist = false">
			<BlockList
				:onPickBlock="onPickBlock"
				:webBlockTypes="webBlockTypes"
				scope="webpage"
			/>
		</Modal>
</template>

<style scoped>
.aspect-w-4 {
	aspect-ratio: 4 / 3;
}
</style>
