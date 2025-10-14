<script setup lang="ts">
import { ref } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faDraftingCompass } from '@fortawesome/free-solid-svg-icons';
import TiptapLinkDialog from "@/Components/Forms/Fields/BubleTextEditor/TiptapCustomLinkDialog.vue";


const props = defineProps({
    editor : {
        type: Object,
        required: true,
    },
    node: {
        type: Object,
        required: true,
    },
    updateAttributes: {
        type: Function,
        required: true,
    },
});

const showLinkDialog = ref<boolean>(false);

const openUrl = () => {
    const url = props.node.attrs.url;
    if (url) window.open(url, '_blank');
};

const openWorkshop = () => {
    const workshopUrl = props.node.attrs.workshop;
    if (workshopUrl) window.open(workshopUrl, '_blank');
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openUrl();
    }
};


const updateValue = (value) => {
    props.updateAttributes({...props.node.attrs, ...value, id : value.id.id});
};


</script>

<template>
    <NodeViewWrapper class="inline">
        <span v-if="props.editor.view.editable"
            class="custom-link" 
            role="button" 
            tabindex="0" 
            @click="showLinkDialog = true" 
            @keydown="handleKeydown"
            :aria-label="`Open ${props.node.attrs.content || 'link'}`"
        >
            {{ props.node.attrs.content }}
            <FontAwesomeIcon 
                v-if="props.node.attrs.type == 'internal'" 
                @click.stop="openWorkshop"
                :icon="faDraftingCompass" 
                class="icon" 
            />
        </span>
        <a v-else :href="props.node.attrs.url" target="_blank" >{{ props.node.attrs.content }}</a>
    </NodeViewWrapper>
    <TiptapLinkDialog 
        v-if="showLinkDialog" 
        :show="showLinkDialog" 
        @close="() => { showLinkDialog = false }" 
        :attribut="{...props.node.attrs, id:  {...props.node.attrs}}" 
        @update="updateValue"
    />
</template>


<style scoped lang="scss">
.custom-link {
    color: blue;
    cursor: pointer;
}

.custom-link:hover {
    color: darkblue;
}

.icon {
    margin-left: 5px;
    cursor: pointer;
}
</style>
