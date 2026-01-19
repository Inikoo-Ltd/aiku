<script setup lang="ts">
const props = defineProps<{
    label: string
}>()
</script>

<template>
    <div class="text-center">
        <span class="sticker" :data-text="label">
            <span>{{ label }}</span>
        </span>
<!-- <br />
        <span class="sticker" data-text="Steph" style="--shine-angle: 8deg;"><span>Steph</span></span> -->
    </div>
</template>

<style lang="scss" scoped>
// Kudos to Lynn for assisting in fixing the original solution!
// https://codepen.io/lynnandtonic/pen/RwrzzjQ

/** DO YOU LOVE CSS? 
Share your love with the rest of the CSS community by contributing to Style Stage - a modern CSS showcase styled by community contributions
@link stylestage.dev 
**/

$t: rgba(255, 0, 0, 0);
$w: rgba(255, 255, 255, 0.2);

* {
    box-sizing: border-box;
}

.sticker {
    --c1: #674300;
    // --c2: #f1c40f;
    // --c3: #f1c40f;
    // --c4: #0bd900;
    // --c5: #0bd900;
    --c2: var(--c1);
    --c3: var(--c1);
    --c4: var(--c1);
    --c5: var(--c1);
    --shine-angle: 15deg;
    display: inline-grid;
    grid-template-areas: "text";
    place-items: center;
    font-family: "Alegreya Sans SC", sans-serif;
    font-weight: 900;
    font-style: italic;
    // font-size: clamp(3rem, 15vw, 10rem);
    text-transform: uppercase;
    color: var(--c5);

    // &-lg {
    //     font-size: clamp(6rem, 30vw, 20rem);
    // }

    span {
        background: linear-gradient(var(--shine-angle),
                $t 0%,
                $t 35%,
                $w 49.95%,
                $w 50.15%,
                $t 65%,
                $t ),
            linear-gradient(to right,
                var(--c1),
                var(--c2),
                var(--c3),
                var(--c4),
                var(--c5));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        -webkit-text-stroke: 0.01em rgba(0, 0, 0, 0.6);
        // FUn fact - letter-spacing messes with the ability of the
        // gradient to cover all the text :(
    }

    &>*,
    &::before,
    &::after {
        grid-area: text;
    }

    &::before,
    &::after {
        content: attr(data-text);
        color: #fff;
    }

    &::before {
        -webkit-text-stroke: 0.21em white;
        // background: no-repeat linear-gradient(white, white) 15% 50% / 85% 60%;

        // Original failed attempt
        // letter-spacing: -0.04em;
        // transform: scale(1.12) translateX(-0.02em) translateY(0.002em);
        // text-shadow: 0.002em 0.002em 0.02em rgba(0, 0, 0, 0.75);
        // -webkit-text-stroke: 0.001em rgba(0, 0, 0, 0.6);
    }

    &::after {
        text-shadow: 0.07em 0.08em 0.05em rgba(0, 0, 0, 0.75),
            -0.07em -0.05em 0.05em rgba(0, 0, 0, 0.75);
        z-index: -2;
    }
}

</style>