<template>
    <div v-if="showMenu" class="image-settings-menu">
      <p>Text wrapping</p>
      <div class="options">
        <button @click="updateImage('none')">In line with text</button>
        <button @click="updateImage('left')">Wrap Left</button>
        <button @click="updateImage('right')">Wrap Right</button>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, watch } from 'vue';
  
  const props = defineProps({
    editor: Object,
  });
  
  const showMenu = ref(false);
  const selectedImage = ref(null);
  
  const updateImage = (float) => {
    if (!selectedImage.value) return;
    props.editor.chain().focus().updateAttributes('image', { float }).run();
  };
  
  watch(
    () => props.editor?.state?.selection,
    (selection) => {
      const node = props.editor?.state?.selection.node;
      showMenu.value = node?.type?.name === 'image';
      selectedImage.value = showMenu.value ? node : null;
    }
  );
  </script>
  
  <style>
  .image-settings-menu {
    position: absolute;
    background: white;
    padding: 10px;
    border: 1px solid #ddd;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    z-index: 1000;
  }
  .options button {
    display: block;
    margin: 5px 0;
  }
  </style>
  