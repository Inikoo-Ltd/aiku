<script setup lang="ts">
import { onMounted, ref } from 'vue'

const props = defineProps<{
    publicSiteUrl: string
}>()

type Routine = 'patrol' | 'dance' | 'dash' | 'romance' | 'duel'

const routines: Routine[] = ['patrol', 'dance', 'dash', 'romance', 'duel']
const soloRoutines: Routine[] = ['patrol', 'dance', 'dash']
const activeRoutine = ref<Routine | null>(null)
const prefersReducedMotion = ref(false)
let routineIndex = 0
let pendingFrame: number | null = null

const publicUrl = (path: string): string => `${props.publicSiteUrl}${path}`

const playNextRoutine = () => {
    if (prefersReducedMotion.value) {
        return
    }

    activeRoutine.value = null
    if (pendingFrame !== null) {
        window.cancelAnimationFrame(pendingFrame)
    }

    const routine = routines[routineIndex % routines.length]
    routineIndex += 1
    pendingFrame = window.requestAnimationFrame(() => {
        activeRoutine.value = routine
        pendingFrame = null
    })
}

onMounted(() => {
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
})
</script>

<template>
    <footer class="auth-mascot-footer">
        <span
            v-if="activeRoutine && soloRoutines.includes(activeRoutine)"
            class="footer-mascot footer-character"
            :data-routine="activeRoutine"
            aria-hidden="true"
            @animationend.self="activeRoutine = null"
        >
            <span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span>
        </span>

        <span
            v-if="activeRoutine === 'romance'"
            class="footer-romance"
            aria-hidden="true"
            @animationend.self="activeRoutine = null"
        >
            <span class="footer-romance__partner footer-romance__partner--left footer-character">
                <span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span>
            </span>
            <span class="footer-romance__partner footer-romance__partner--right footer-character">
                <span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span>
            </span>
            <span class="footer-romance__heart">♥</span>
            <span class="footer-romance__kid footer-romance__kid--one footer-character"><span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span></span>
            <span class="footer-romance__kid footer-romance__kid--two footer-character"><span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span></span>
            <span class="footer-romance__kid footer-romance__kid--three footer-character"><span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span></span>
        </span>

        <span
            v-if="activeRoutine === 'duel'"
            class="footer-duel"
            aria-hidden="true"
            @animationend.self="activeRoutine = null"
        >
            <span class="footer-duel__fighter footer-duel__fighter--left footer-character"><span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span></span>
            <span class="footer-duel__fighter footer-duel__fighter--right footer-character"><span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span></span>
            <span class="footer-duel__bullet footer-duel__bullet--left"></span>
            <span class="footer-duel__bullet footer-duel__bullet--right"></span>
            <span class="footer-duel__impact">✦</span>
        </span>

        <div class="auth-mascot-footer__content">
            <nav class="auth-mascot-footer__links" aria-label="About aiku">
                <a href="https://github.com/Inikoo-Ltd/aiku" rel="noopener">
                    <svg viewBox="0 0 16 16" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                    GitHub
                </a>
                <a :href="publicUrl('/blog')">Engineering notes</a>
                <a :href="publicUrl('/docs')">Documentation</a>
                <a :href="publicUrl('/feed.xml')">RSS</a>
                <a :href="publicUrl('/sitemap.xml')">Sitemap</a>
                <a href="mailto:hello@aiku.io">hello@aiku.io</a>
            </nav>

            <div class="auth-mascot-footer__license">
                <button
                    class="footer-animation-trigger"
                    type="button"
                    :disabled="prefersReducedMotion"
                    aria-label="Play the next mascot animation"
                    title="Play mascot animation"
                    @click="playNextRoutine"
                >
                    <span class="footer-mascot-art"><img src="/art/invader-sketch.svg" alt=""></span>
                </button>
                <span>aiku</span>
                <span>is open source software (<a href="https://github.com/Inikoo-Ltd/aiku/blob/main/LICENSE" rel="noopener">AGPL-3.0</a>).</span>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.auth-mascot-footer {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 20;
    border-top: 1px solid rgb(28 27 34 / 12%);
    background: rgb(251 250 246 / 46%);
    color: rgb(28 27 34 / 68%);
    font-size: 11px;
    line-height: 1.35;
    backdrop-filter: blur(10px);
}

.auth-mascot-footer__content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px 24px;
    width: 100%;
    padding: 10px 24px 11px;
}

.auth-mascot-footer__links,
.auth-mascot-footer__license,
.auth-mascot-footer__links a {
    display: flex;
    align-items: center;
}

.auth-mascot-footer__links {
    flex-wrap: wrap;
    gap: 7px 17px;
}

.auth-mascot-footer__links a {
    gap: 5px;
}

.auth-mascot-footer__license {
    flex: none;
    gap: 6px;
    white-space: nowrap;
}

.auth-mascot-footer a {
    color: inherit;
    text-decoration: none;
}

.auth-mascot-footer a:hover {
    color: rgb(28 27 34 / 92%);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.footer-animation-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 27px;
    margin: -6px 0;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
    transition: transform .18s ease;
}

.footer-animation-trigger:hover {
    transform: translateY(-1px) scale(1.07);
}

.footer-animation-trigger:focus-visible {
    border-radius: 4px;
    outline: 2px solid #3730a3;
    outline-offset: 2px;
}

.footer-animation-trigger:disabled {
    cursor: default;
    opacity: .65;
}

.footer-animation-trigger .footer-mascot-art {
    width: 23px;
    height: 26px;
}

.footer-mascot-art,
.footer-mascot-art img {
    display: block;
}

.footer-mascot-art img {
    width: 100%;
    height: 100%;
}

.footer-mascot {
    --gait-speed: .42s;
    position: absolute;
    z-index: 1;
    top: -45px;
    left: 0;
    width: 40px;
    height: 44px;
    pointer-events: none;
    opacity: 0;
}

.footer-mascot > .footer-mascot-art {
    width: 100%;
    height: 100%;
    transform-origin: 50% 88%;
}

.footer-character {
    --gait-speed: .42s;
}

.footer-character .footer-mascot-art img {
    transform-origin: 50% 88%;
    animation: aiku-character-gait var(--gait-speed) steps(2, end) infinite;
}

.footer-mascot[data-routine="patrol"] {
    animation: aiku-footer-patrol 18s linear both;
}

.footer-mascot[data-routine="patrol"] > .footer-mascot-art {
    animation: aiku-footer-patrol-stunt 18s ease-in-out both;
}

.footer-mascot[data-routine="dance"] {
    left: calc(50% - 20px);
    --gait-speed: .28s;
    animation: aiku-footer-appearance 7s ease both;
}

.footer-mascot[data-routine="dance"] > .footer-mascot-art {
    animation: aiku-footer-dance 7s ease-in-out both;
}

.footer-mascot[data-routine="dash"] {
    --gait-speed: .2s;
    animation: aiku-footer-dash 6s cubic-bezier(.3, .05, .65, .95) both;
}

.footer-mascot[data-routine="dash"] > .footer-mascot-art {
    animation: aiku-footer-hop .48s ease-in-out infinite alternate;
}

.footer-romance,
.footer-duel {
    position: absolute;
    z-index: 1;
    top: -45px;
    right: 0;
    left: 0;
    height: 44px;
    pointer-events: none;
}

.footer-romance {
    animation: aiku-romance-scene 16s linear both;
}

.footer-romance__partner,
.footer-duel__fighter {
    position: absolute;
    bottom: 0;
    width: 40px;
    height: 44px;
}

.footer-romance__partner .footer-mascot-art,
.footer-romance__kid .footer-mascot-art,
.footer-duel__fighter .footer-mascot-art {
    width: 100%;
    height: 100%;
}

.footer-romance__partner--left { animation: aiku-romance-left 16s ease-in-out both; }
.footer-romance__partner--right { animation: aiku-romance-right 16s ease-in-out both; }
.footer-romance__heart { position: absolute; left: calc(50% - 7px); top: -8px; color: #e879a0; font-family: Georgia, serif; font-size: 20px; line-height: 1; animation: aiku-romance-heart 16s ease-out both; }
.footer-romance__kid { --gait-speed: .2s; position: absolute; bottom: 0; left: calc(50% - 24px); width: 19px; height: 22px; opacity: 0; animation: aiku-romance-kid 16s ease-in both; }
.footer-romance__kid--two { animation-delay: .32s; }
.footer-romance__kid--three { animation-delay: .64s; }

.footer-duel { animation: aiku-duel-scene 13s linear both; }
.footer-duel__fighter--left { animation: aiku-duel-left 13s ease-in-out both; }
.footer-duel__fighter--right { animation: aiku-duel-right 13s ease-in-out both; }
.footer-duel__fighter--left .footer-mascot-art img { filter: hue-rotate(-20deg) saturate(1.15); }
.footer-duel__fighter--right .footer-mascot-art img { filter: hue-rotate(145deg) saturate(1.45); }
.footer-duel__bullet { position: absolute; top: 23px; width: 8px; height: 3px; border-radius: 1px; background: #f3c04a; box-shadow: 0 0 7px rgb(243 192 74 / 90%); opacity: 0; }
.footer-duel__bullet--left { animation: aiku-duel-bullet-left 13s linear both; }
.footer-duel__bullet--right { animation: aiku-duel-bullet-right 13s linear both; }
.footer-duel__impact { position: absolute; left: calc(12% + 22px); top: 9px; color: #ef4444; font-size: 20px; line-height: 1; opacity: 0; animation: aiku-duel-impact 13s ease-out both; }

@keyframes aiku-character-gait { 0% { transform: translateY(0) rotate(-1.5deg); } 100% { transform: translateY(-2px) rotate(1.5deg); } }
@keyframes aiku-footer-patrol { 0% { left: 0; opacity: 0; transform: scaleX(1); } 3% { opacity: 1; } 34%, 50% { left: calc(50% - 20px); opacity: 1; transform: scaleX(1); } 72%, 76% { left: calc(100% - 40px); opacity: 1; transform: scaleX(1); } 79% { left: calc(100% - 40px); opacity: 1; transform: scaleX(-1); } 97% { left: 0; opacity: 1; transform: scaleX(-1); } 100% { left: 0; opacity: 0; transform: scaleX(-1); } }
@keyframes aiku-footer-patrol-stunt { 0%, 34%, 49%, 100% { transform: translateY(0) rotate(0) scale(1); } 37% { transform: translateY(-4px) rotate(-10deg) scale(1.04); } 40% { transform: translateY(0) rotate(10deg) scale(1); } 43% { transform: translateY(-12px) rotate(180deg) scale(1.12); } 46% { transform: translateY(0) rotate(360deg) scale(1); } }
@keyframes aiku-footer-appearance { 0%, 100% { opacity: 0; } 8%, 92% { opacity: 1; } }
@keyframes aiku-footer-dance { 0%, 8%, 92%, 100% { transform: translateY(0) rotate(0) scale(1); } 18% { transform: translateY(-5px) rotate(-12deg) scale(1.05); } 28% { transform: translateY(0) rotate(12deg) scale(.96); } 42% { transform: translateY(-14px) rotate(180deg) scale(1.14); } 56% { transform: translateY(0) rotate(360deg) scale(1); } 68% { transform: translateY(-6px) rotate(348deg) scale(1.08); } 80% { transform: translateY(0) rotate(372deg) scale(1); } }
@keyframes aiku-footer-dash { 0% { left: 0; opacity: 0; transform: scaleX(1); } 8% { opacity: 1; } 48% { left: calc(100% - 40px); opacity: 1; transform: scaleX(1); } 55% { left: calc(100% - 40px); transform: scaleX(-1); } 92% { left: 0; opacity: 1; transform: scaleX(-1); } 100% { left: 0; opacity: 0; transform: scaleX(-1); } }
@keyframes aiku-footer-hop { 0% { transform: translateY(0) rotate(-2deg); } 100% { transform: translateY(-4px) rotate(2deg); } }
@keyframes aiku-romance-scene { 0%, 100% { opacity: 0; } 3%, 96% { opacity: 1; } }
@keyframes aiku-romance-left { 0% { left: -42px; opacity: 0; transform: rotate(0); } 5% { opacity: 1; } 26% { left: calc(50% - 42px); transform: rotate(0); } 32%, 38% { left: calc(50% - 39px); transform: translateX(3px) rotate(9deg); } 43% { left: calc(50% - 42px); transform: translateY(-5px) rotate(-5deg); } 50%, 72% { left: calc(50% - 42px); transform: translateY(0) rotate(0); } 78%, 84% { left: calc(50% - 42px); transform: rotate(-10deg); } 92%, 100% { left: calc(50% - 42px); opacity: 0; transform: rotate(0); } }
@keyframes aiku-romance-right { 0% { left: calc(100% + 2px); opacity: 0; transform: scaleX(-1); } 5% { opacity: 1; } 26% { left: calc(50% + 2px); transform: scaleX(-1); } 32%, 38% { left: calc(50% - 1px); transform: translateX(-3px) rotate(-9deg) scaleX(-1); } 44%, 54% { left: calc(50% + 2px); transform: translateY(0) scaleX(-1); } 58% { left: calc(50% + 2px); transform: translateY(-5px) rotate(-8deg) scaleX(-1); } 63% { left: calc(50% + 2px); transform: translateY(0) rotate(8deg) scaleX(-1); } 68% { left: calc(50% + 2px); transform: scaleX(1); } 90%, 100% { left: calc(100% + 45px); opacity: 1; transform: scaleX(1); } }
@keyframes aiku-romance-heart { 0%, 27% { opacity: 0; transform: translateY(8px) scale(.3); } 33% { opacity: 1; transform: translateY(0) scale(1.15); } 40% { opacity: 0; transform: translateY(-16px) scale(.8); } 100% { opacity: 0; } }
@keyframes aiku-romance-kid { 0%, 44% { left: calc(50% - 24px); opacity: 0; transform: translateY(8px) scale(.2); } 49% { opacity: 1; transform: translateY(-5px) scale(.65); } 54%, 66% { left: calc(50% - 16px); opacity: 1; transform: translateY(0) scale(.65); } 92%, 100% { left: calc(100% + 20px); opacity: 1; transform: translateY(0) scale(.65); } }
@keyframes aiku-duel-scene { 0%, 100% { opacity: 0; } 3%, 96% { opacity: 1; } }
@keyframes aiku-duel-left { 0% { left: -42px; opacity: 0; transform: scaleX(1); } 5% { opacity: 1; } 20%, 48% { left: 12%; transform: translate(0, 0) scaleX(1); } 52% { left: 12%; transform: translateX(-4px) scaleX(1); } 57% { left: 12%; transform: translate(0, 0) scaleX(1); } 62% { left: 12%; transform: translateY(-5px) rotate(-20deg) scaleX(1); } 70%, 88% { left: 12%; opacity: 1; transform: translate(-7px, 14px) rotate(-90deg) scale(.92); } 96%, 100% { left: 12%; opacity: 0; transform: translate(-7px, 14px) rotate(-90deg) scale(.92); } }
@keyframes aiku-duel-right { 0% { left: calc(100% + 2px); opacity: 0; transform: scaleX(-1); } 5% { opacity: 1; } 20%, 31% { left: calc(88% - 40px); transform: translateY(0) scaleX(-1); } 37%, 43% { left: calc(88% - 40px); transform: translateY(-23px) rotate(-7deg) scaleX(-1); } 48% { left: calc(88% - 40px); transform: translateY(0) scaleX(-1); } 52% { left: calc(88% - 40px); transform: translateX(4px) scaleX(-1); } 58%, 73% { left: calc(88% - 40px); transform: translate(0, 0) scaleX(-1); } 78% { left: calc(88% - 40px); transform: translateY(-7px) rotate(-8deg) scaleX(-1); } 84%, 96% { left: calc(88% - 40px); opacity: 1; transform: translateY(0) rotate(5deg) scaleX(-1); } 100% { left: calc(88% - 40px); opacity: 0; transform: translateY(0) scaleX(-1); } }
@keyframes aiku-duel-bullet-left { 0%, 25% { left: calc(12% + 31px); opacity: 0; } 27% { opacity: 1; } 44% { left: calc(88% - 30px); opacity: 1; } 45%, 100% { left: calc(88% - 30px); opacity: 0; } }
@keyframes aiku-duel-bullet-right { 0%, 50% { left: calc(88% - 10px); opacity: 0; } 52% { opacity: 1; } 62% { left: calc(12% + 30px); opacity: 1; } 63%, 100% { left: calc(12% + 30px); opacity: 0; } }
@keyframes aiku-duel-impact { 0%, 59% { opacity: 0; transform: scale(.2) rotate(0); } 62% { opacity: 1; transform: scale(1.35) rotate(35deg); } 68%, 100% { opacity: 0; transform: scale(.5) rotate(90deg); } }

@media (min-width: 640px) {
    .auth-mascot-footer {
        font-size: 12px;
    }

    .auth-mascot-footer__content {
        padding-right: 32px;
        padding-left: 32px;
    }
}

@media (max-width: 760px) {
    .auth-mascot-footer__content {
        justify-content: center;
    }

    .auth-mascot-footer__links {
        justify-content: center;
    }

    .auth-mascot-footer__license {
        width: 100%;
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .footer-mascot,
    .footer-romance,
    .footer-duel {
        display: none !important;
    }
}
</style>
