<script setup lang="ts">
import { computed, inject, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { GoogleLogin } from "vue3-google-login"
import { router, usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBadgeCheck, faCheck, faCheckCircle, faStar } from "@fal"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { getStyles } from "@/Composables/styles"
import { getRefRedirect } from "@/Composables/Retina/useGetRedirectUrl"

library.add(faBadgeCheck, faCheck, faCheckCircle, faStar)

const props = withDefaults(
	defineProps<{
		fieldValue: any
		theme?: any
		screenType?: "mobile" | "tablet" | "desktop"
		indexBlock?: number | string
		isWorkshop?: boolean
	}>(),
	{
		screenType: "desktop",
		isWorkshop: false,
	}
)

const layout = inject<any>("layout", {})

const rootColorStyle = computed(() => {
	const button = props.fieldValue?.card?.button ?? {}

	return {
		"--rd2-accent": button.color || "var(--theme-color-0, #e87928)",
		"--rd2-accent-dark": button.hover_color || "#c95f17",
		"--rd2-soft": props.fieldValue?.card?.area_background_color || "#f5f3ef",
		"--rd2-line": props.fieldValue?.card?.line_color || "#dedbd5",
		"--rd2-card": props.fieldValue?.card?.background_color || "#ffffff",
	}
})

const heroColorStyle = computed(() => {
	const hero = props.fieldValue?.hero ?? {}

	return {
		"--rd2-hero-text": hero.text_color || "#ffffff",
		"--rd2-overlay": hero.overlay_color || "rgb(17, 24, 32)",
		"--rd2-benefit-border": hero.benefit_border_color || "#c8c6c2",
	}
})

const cardColorStyle = computed(() => {
	const card = props.fieldValue?.card ?? {}

	return {
		"--rd2-ink": card.text_color || "#1d252e",
		"--rd2-muted": card.muted_color || "#58616b",
	}
})

const faqColorStyle = computed(() => {
	const faq = props.fieldValue?.faq ?? {}

	return {
		"--rd2-ink": faq.text_color || "#1d252e",
		"--rd2-muted": faq.muted_color || "#58616b",
		"--rd2-line": faq.line_color || "#dedbd5",
		"--rd2-accent-dark": faq.toggle_color || props.fieldValue?.card?.button?.hover_color || "#c95f17",
	}
})

/**
 * An image is either uploaded through the workshop or given as an address, so a shop can point at
 * an image it already hosts. The uploaded one wins, the address is read as a plain source.
 */
const heroImage = computed(() => {
	const hero = props.fieldValue?.hero

	if (hero?.image) {
		return hero.image
	}

	const address = typeof hero?.image_url === "string" ? hero.image_url.trim() : ""

	return address ? { original: address } : null
})

const benefits = computed(() =>
	props.fieldValue?.hero?.show_benefits === false ? [] : (props.fieldValue?.hero?.benefits ?? [])
)

const checks = computed(() =>
	props.fieldValue?.card?.show_checks === false ? [] : (props.fieldValue?.card?.checks ?? [])
)

const faqItems = computed(() =>
	props.fieldValue?.faq?.show === false ? [] : (props.fieldValue?.faq?.items ?? [])
)

const registerLink = computed(() => {
	const link = props.fieldValue?.card?.button?.link

	return {
		type: link?.type ?? "internal",
		target: link?.target ?? "_self",
		href: link?.href || "/app/registration-form",
		canonical_url: link?.canonical_url,
	}
})

/**
 * Registration with Google follows the retina register page: the Google token is posted to the
 * login endpoint, a known customer is sent on to where they were going and anybody else continues
 * to the Google registration form with that token.
 */
const page = usePage<any>()

const googleClientId = computed(() => page?.props?.google?.client_id || layout?.iris?.google?.client_id)
const isGoogleVisible = computed(
	() => props.fieldValue?.card?.google?.show !== false && (props.isWorkshop || !!googleClientId.value)
)

const isLoadingGoogle = ref(false)

interface GoogleLoginResponse {
	access_token: string
}

const onGoogleLoginFailed = () => {
	isLoadingGoogle.value = false
	notify({
		title: trans("Something went wrong"),
		text: trans("Failed to login with Google. Please contact administrator."),
		type: "error",
	})
}

const onCallbackGoogleLogin = async (e: GoogleLoginResponse) => {
	isLoadingGoogle.value = true

	try {
		const data = await axios.post(route("retina.login_google", {}), {
			google_access_token: e.access_token,
			tiktok_code: route().queryParams?.tiktok_code,
		})

		if (data.status === 200) {
			if (data.data.logged_in) {
				window.location.href = await getRefRedirect()
			} else {
				router.get(
					route("retina.register_from_google"),
					{
						google_access_token: e.access_token,
					},
					{
						onStart: () => {
							isLoadingGoogle.value = true
						},
					}
				)
			}

			return
		}

		onGoogleLoginFailed()
	} catch (error: any) {
		onGoogleLoginFailed()
	}
}
</script>

<template>
	<section :id="fieldValue?.id ? fieldValue.id : 'register-dashboard-2-' + indexBlock" component="register-dashboard-2"
		class="rd2-root" data-rd-panel="layout" :style="{
			...rootColorStyle,
			...getStyles(fieldValue?.container?.properties, screenType),
		}">
		<div class="rd2-hero">
			<div class="rd2-shell">
				<div class="rd2-story" data-rd-panel="hero" :style="heroColorStyle">
					<Image v-if="heroImage" :src="heroImage" :alt="fieldValue?.hero?.image_alt ?? ''" :imageCover="true"
						class="rd2-photo" />

					<div class="rd2-copy">
						<div v-if="fieldValue?.hero?.eyebrow || isWorkshop" class="rd2-eyebrow">
							<slot name="editable" :path="['hero', 'eyebrow']" :value="fieldValue?.hero?.eyebrow" placeholder="Eyebrow">
								<span v-html="fieldValue?.hero?.eyebrow" />
							</slot>
						</div>

						<h1 v-if="fieldValue?.hero?.title || isWorkshop">
							<slot name="editable" :path="['hero', 'title']" :value="fieldValue?.hero?.title" placeholder="Title">
								<span v-html="fieldValue?.hero?.title" />
							</slot>
						</h1>

						<div v-if="fieldValue?.hero?.intro || isWorkshop" class="rd2-intro">
							<slot name="editable" :path="['hero', 'intro']" :value="fieldValue?.hero?.intro" placeholder="Intro">
								<div v-html="fieldValue?.hero?.intro" />
							</slot>
						</div>

						<div v-if="benefits.length" class="rd2-benefits">
							<div v-for="(benefit, index) in benefits" :key="index" class="rd2-benefit" data-rd-panel="benefits" :data-rd-index="index">
								<strong class="rd2-benefit-title">
									<slot name="editable" :path="['hero', 'benefits', index, 'title']" :value="benefit.title" placeholder="Benefit">
										<span v-html="benefit.title" />
									</slot>
								</strong>
								<span v-if="benefit.text || isWorkshop" class="rd2-benefit-text">
									<slot name="editable" :path="['hero', 'benefits', index, 'text']" :value="benefit.text" placeholder="Benefit description">
										<span v-html="benefit.text" />
									</slot>
								</span>
							</div>
						</div>
					</div>
				</div>

				<div class="rd2-action">
					<div class="rd2-card" data-rd-panel="card" :style="cardColorStyle">
						<div v-if="isLoadingGoogle" class="rd2-card-loading">
							<LoadingIcon class="text-4xl" />
						</div>

						<h2 v-if="fieldValue?.card?.title || isWorkshop">
							<slot name="editable" :path="['card', 'title']" :value="fieldValue?.card?.title" placeholder="Title">
								<span v-html="fieldValue?.card?.title" />
							</slot>
						</h2>

						<div v-if="fieldValue?.card?.intro || isWorkshop" class="rd2-card-intro">
							<slot name="editable" :path="['card', 'intro']" :value="fieldValue?.card?.intro" placeholder="Intro">
								<div v-html="fieldValue?.card?.intro" />
							</slot>
						</div>

						<div v-if="checks.length" class="rd2-checks">
							<div v-for="(check, index) in checks" :key="index" class="rd2-check" data-rd-panel="checks" :data-rd-index="index">
								<span class="rd2-check-icon" aria-hidden="true">
									<FontAwesomeIcon :icon="check.icon || faCheck" fixed-width />
								</span>
								<div class="rd2-check-text">
									<slot name="editable" :path="['card', 'checks', index, 'text']" :value="check.text" placeholder="Check">
										<span v-html="check.text" />
									</slot>
								</div>
							</div>
						</div>

						<div v-if="fieldValue?.card?.button?.show !== false" data-rd-panel="register-button">
							<LinkIris :href="registerLink.href" :type="registerLink.type" :target="registerLink.target"
								:canonical_url="registerLink.canonical_url" class="rd2-primary">
								<slot name="editable" :path="['card', 'button', 'label']" :value="fieldValue?.card?.button?.label" placeholder="Create my wholesale account">
									<span v-html="fieldValue?.card?.button?.label || trans('Create my wholesale account')" />
								</slot>
							</LinkIris>

							<div v-if="fieldValue?.card?.button?.note || isWorkshop" class="rd2-time">
								<slot name="editable" :path="['card', 'button', 'note']" :value="fieldValue?.card?.button?.note" placeholder="Button note">
									<span v-html="fieldValue?.card?.button?.note" />
								</slot>
							</div>
						</div>

						<div v-if="isGoogleVisible" class="rd2-google" data-rd-panel="google">
							<div v-if="fieldValue?.card?.google?.note || isWorkshop" class="rd2-google-note">
								<slot name="editable" :path="['card', 'google', 'note']" :value="fieldValue?.card?.google?.note" placeholder="Google note (optional)">
									<span v-html="fieldValue?.card?.google?.note" />
								</slot>
							</div>

							<button v-if="isWorkshop" type="button" class="rd2-google-button">
								<svg class="rd2-google-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"
									aria-hidden="true">
									<path fill="#EA4335"
										d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
									<path fill="#4285F4"
										d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
									<path fill="#FBBC05"
										d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
									<path fill="#34A853"
										d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
								</svg>
								<span>
									<slot name="editable" :path="['card', 'google', 'label']" :value="fieldValue?.card?.google?.label" placeholder="Register with Google">
										<span v-html="fieldValue?.card?.google?.label || trans('Register with Google')" />
									</slot>
								</span>
							</button>

							<GoogleLogin v-else :clientId="googleClientId" popup-type="TOKEN"
								:callback="(e: GoogleLoginResponse) => onCallbackGoogleLogin(e)">
								<template #default>
									<div class="rd2-google-button">
										<svg class="rd2-google-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"
									aria-hidden="true">
									<path fill="#EA4335"
										d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
									<path fill="#4285F4"
										d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
									<path fill="#FBBC05"
										d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
									<path fill="#34A853"
										d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
								</svg>
										<span v-html="fieldValue?.card?.google?.label || trans('Register with Google')" />
									</div>
								</template>
							</GoogleLogin>
						</div>

						<div v-if="fieldValue?.card?.login_note || isWorkshop" class="rd2-login-row" data-rd-panel="card">
							<slot name="editable" :path="['card', 'login_note']" :value="fieldValue?.card?.login_note" placeholder="Login note">
								<div v-html="fieldValue?.card?.login_note" />
							</slot>
						</div>

						<div v-if="fieldValue?.card?.help_note || isWorkshop" class="rd2-help" data-rd-panel="card">
							<slot name="editable" :path="['card', 'help_note']" :value="fieldValue?.card?.help_note" placeholder="Help note">
								<div v-html="fieldValue?.card?.help_note" />
							</slot>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div v-if="faqItems.length || (isWorkshop && fieldValue?.faq?.show !== false)" class="rd2-faq" data-rd-panel="faq" :style="faqColorStyle">
			<div class="rd2-faq-header">
				<h2 v-if="fieldValue?.faq?.title || isWorkshop">
					<slot name="editable" :path="['faq', 'title']" :value="fieldValue?.faq?.title" placeholder="FAQ title">
						<span v-html="fieldValue?.faq?.title" />
					</slot>
				</h2>
				<div v-if="fieldValue?.faq?.subtitle || isWorkshop" class="rd2-faq-subtitle">
					<slot name="editable" :path="['faq', 'subtitle']" :value="fieldValue?.faq?.subtitle" placeholder="FAQ subtitle">
						<span v-html="fieldValue?.faq?.subtitle" />
					</slot>
				</div>
			</div>

			<div v-if="isWorkshop && !faqItems.length" class="rd2-faq-empty" data-rd-panel="faq-items">
				{{ trans("No questions yet. Add them in FAQ questions.") }}
			</div>

			<div v-if="faqItems.length" class="rd2-faq-list">
				<details v-for="(item, index) in faqItems" :key="index" :open="isWorkshop" data-rd-panel="faq-items" :data-rd-index="index">
					<summary @click.capture="isWorkshop && $event.preventDefault()">
						<slot name="editable" :path="['faq', 'items', index, 'question']" :value="item.question" placeholder="Question">
							<span v-html="item.question" />
						</slot>
					</summary>
					<div class="rd2-faq-answer">
						<slot name="editable" :path="['faq', 'items', index, 'answer']" :value="item.answer" placeholder="Answer">
							<div v-html="item.answer" />
						</slot>
					</div>
				</details>
			</div>
		</div>
	</section>
</template>

<style>
.rd2-root {
	width: 100%;
	margin: 0;
	background: var(--rd2-card);
	color: var(--rd2-ink);
	font-family: Arial, Helvetica, sans-serif;
	line-height: 1.5;
}

.rd2-root *,
.rd2-root *::before,
.rd2-root *::after {
	box-sizing: border-box;
}

.rd2-root h1,
.rd2-root h2,
.rd2-root p {
	margin: 0;
}

.rd2-root a {
	color: inherit;
}

.rd2-root h1 p,
.rd2-root h2 p,
.rd2-eyebrow p,
.rd2-primary p,
.rd2-google-note p,
.rd2-google-button p,
.rd2-time p,
.rd2-check-text p,
.rd2-benefits p,
.rd2-faq-subtitle p,
.rd2-root summary p {
	display: inline;
}

.rd2-root .rd-editable,
.rd2-root .rd-editable .editor-class,
.rd2-root .rd-editable .editor-class p {
	font-size: inherit;
	font-weight: inherit;
	font-family: inherit;
	line-height: inherit;
	letter-spacing: inherit;
	color: inherit;
	text-align: inherit;
}

.rd2-hero {
	width: 100%;
	background: var(--rd2-soft);
	border-bottom: 1px solid var(--rd2-line);
}

.rd2-shell {
	display: grid;
	grid-template-columns: minmax(0, 1.05fr) minmax(380px, 0.95fr);
	min-height: 670px;
	max-width: 1440px;
	margin: 0 auto;
}

.rd2-story {
	position: relative;
	display: flex;
	min-height: 670px;
	overflow: hidden;
	isolation: isolate;
}

.rd2-story::after {
	position: absolute;
	inset: 0;
	z-index: -1;
	background: linear-gradient(90deg,
			color-mix(in srgb, var(--rd2-overlay) 88%, transparent) 0%,
			color-mix(in srgb, var(--rd2-overlay) 67%, transparent) 54%,
			color-mix(in srgb, var(--rd2-overlay) 18%, transparent) 100%);
	content: "";
}

.rd2-photo {
	position: absolute;
	inset: 0;
	z-index: -2;
	width: 100%;
	height: 100%;
}

.rd2-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}

.rd2-copy {
	align-self: flex-end;
	width: min(660px, 100%);
	padding: clamp(42px, 6vw, 84px);
	color: var(--rd2-hero-text);
}

.rd2-eyebrow {
	margin: 0 0 14px;
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.14em;
	text-transform: uppercase;
}

.rd2-root h1 {
	max-width: 620px;
	color: var(--rd2-hero-text);
	font-size: clamp(38px, 4.5vw, 66px);
	font-weight: 700;
	letter-spacing: -0.035em;
	line-height: 1.03;
}

.rd2-intro {
	max-width: 590px;
	margin: 22px 0 0;
	opacity: 0.92;
	font-size: clamp(17px, 1.4vw, 20px);
	line-height: 1.55;
}

.rd2-benefits {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 0;
	margin: 34px 0 0;
}

.rd2-benefit {
	display: flex;
	min-width: 0;
	flex-direction: column;
	gap: 6px;
	margin-left: -1px;
	padding: 4px 20px;
	border-left: 1px solid var(--rd2-benefit-border);
	border-right: 1px solid var(--rd2-benefit-border);
}

.rd2-benefit-title {
	display: block;
	font-size: 17px;
	line-height: 1.25;
}

.rd2-benefit-text {
	display: block;
	opacity: 0.8;
	font-size: 13px;
	line-height: 1.4;
}

.rd2-action {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: clamp(36px, 5vw, 72px);
	background: var(--rd2-soft);
}

.rd2-card {
	position: relative;
	width: 100%;
	max-width: 520px;
	padding: clamp(30px, 4vw, 52px);
	background: var(--rd2-card);
	border: 1px solid var(--rd2-line);
	border-radius: 4px;
	box-shadow: 0 16px 40px rgba(29, 37, 46, 0.08);
}

.rd2-card h2 {
	color: var(--rd2-ink);
	font-size: clamp(27px, 3vw, 38px);
	font-weight: 700;
	letter-spacing: -0.025em;
	line-height: 1.15;
}

.rd2-card-intro {
	margin: 14px 0 0;
	color: var(--rd2-muted);
	font-size: 16px;
}

.rd2-checks {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin: 26px 0 30px;
}

.rd2-check {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	color: var(--rd2-ink);
	font-size: 15px;
}

.rd2-check-icon {
	display: flex;
	flex: 0 0 20px;
	width: 20px;
	height: 20px;
	margin-top: 2px;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: color-mix(in srgb, var(--rd2-accent) 12%, transparent);
	color: var(--rd2-accent-dark);
	font-size: 11px;
}

.rd2-check-text {
	min-width: 0;
}

.rd2-primary {
	display: flex;
	width: 100%;
	min-height: 52px;
	align-items: center;
	justify-content: center;
	padding: 14px 22px;
	border: 2px solid var(--rd2-accent);
	border-radius: 3px;
	background: var(--rd2-accent);
	color: #ffffff !important;
	font-size: 16px;
	font-weight: 700;
	text-align: center;
	text-decoration: none;
	transition: background-color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.rd2-primary:hover {
	border-color: var(--rd2-accent-dark);
	background: var(--rd2-accent-dark);
	transform: translateY(-1px);
}

.rd2-primary:focus-visible,
.rd2-root summary:focus-visible,
.rd2-login-row a:focus-visible,
.rd2-faq-answer a:focus-visible {
	outline: 3px solid color-mix(in srgb, var(--rd2-accent) 35%, transparent);
	outline-offset: 3px;
}

.rd2-card-loading {
	position: absolute;
	inset: 0;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(0, 0, 0, 0.5);
	color: #ffffff;
	border-radius: 4px;
}

.rd2-google {
	margin-top: 22px;
}

.rd2-google .g-btn-wrapper {
	width: 100%;
}

.rd2-google-note {
	margin-bottom: 10px;
	color: var(--rd2-muted);
	font-size: 15px;
	text-align: center;
}

.rd2-google-button {
	position: relative;
	display: flex;
	width: 100%;
	min-height: 52px;
	align-items: center;
	justify-content: center;
	gap: 10px;
	padding: 12px 18px;
	border: 1px solid var(--rd2-ink);
	border-radius: 3px;
	background: var(--rd2-card);
	color: var(--rd2-ink);
	font-size: 16px;
	font-weight: 700;
	cursor: pointer;
	transition: background-color 160ms ease;
}

.rd2-google-button:hover {
	background: var(--rd2-soft);
}

.rd2-google-logo {
	position: absolute;
	left: 16px;
	width: 20px;
	height: 20px;
}

.rd2-time {
	margin: 11px 0 0;
	color: var(--rd2-muted);
	font-size: 13px;
	text-align: center;
}

.rd2-login-row {
	margin: 26px 0 0;
	padding-top: 24px;
	border-top: 1px solid var(--rd2-line);
	color: var(--rd2-muted);
	font-size: 15px;
	text-align: center;
}

.rd2-login-row a {
	color: var(--rd2-accent-dark);
	font-weight: 700;
	text-decoration: underline;
	text-decoration-thickness: 1px;
	text-underline-offset: 3px;
}

.rd2-help {
	margin: 20px 0 0;
	color: var(--rd2-muted);
	font-size: 13px;
	line-height: 1.5;
	text-align: center;
}

.rd2-help a {
	color: var(--rd2-ink);
	text-decoration: underline;
	text-underline-offset: 3px;
}

.rd2-faq {
	max-width: 1100px;
	margin: 0 auto;
	padding: clamp(64px, 8vw, 96px) 24px;
}

.rd2-faq-header {
	max-width: 700px;
	margin-bottom: 36px;
}

.rd2-faq h2 {
	color: var(--rd2-ink);
	font-size: clamp(28px, 3vw, 40px);
	font-weight: 700;
	letter-spacing: -0.025em;
	line-height: 1.18;
}

.rd2-faq-subtitle {
	margin: 12px 0 0;
	color: var(--rd2-muted);
	font-size: 16px;
}

.rd2-faq-empty {
	padding: 22px;
	border: 1px dashed var(--rd2-line);
	color: var(--rd2-muted);
	text-align: center;
}

.rd2-faq-list {
	border-top: 1px solid var(--rd2-line);
}

.rd2-root details {
	border-bottom: 1px solid var(--rd2-line);
}

.rd2-root summary {
	position: relative;
	padding: 22px 48px 22px 0;
	color: var(--rd2-ink);
	cursor: pointer;
	font-size: 17px;
	font-weight: 700;
	list-style: none;
}

.rd2-root summary::-webkit-details-marker {
	display: none;
}

.rd2-root summary::after {
	position: absolute;
	top: 50%;
	right: 8px;
	color: var(--rd2-accent-dark);
	content: "+";
	font-size: 26px;
	font-weight: 400;
	line-height: 1;
	transform: translateY(-50%);
}

.rd2-root details[open] summary::after {
	content: "\2212";
}

.rd2-faq-answer {
	max-width: 820px;
	margin: -6px 0 22px;
	color: var(--rd2-muted);
	font-size: 15px;
	line-height: 1.65;
}

.rd2-faq-answer a {
	color: var(--rd2-accent-dark);
	font-weight: 700;
	text-decoration: underline;
	text-underline-offset: 3px;
}

@media (max-width: 960px) {
	.rd2-shell {
		grid-template-columns: 1fr;
	}

	.rd2-story {
		min-height: 560px;
	}

	.rd2-action {
		padding: 44px 24px;
	}

	.rd2-card {
		max-width: 640px;
	}
}

@media (max-width: 620px) {
	.rd2-story {
		min-height: 520px;
	}

	.rd2-story::after {
		background: linear-gradient(0deg,
				color-mix(in srgb, var(--rd2-overlay) 94%, transparent) 0%,
				color-mix(in srgb, var(--rd2-overlay) 72%, transparent) 70%,
				color-mix(in srgb, var(--rd2-overlay) 38%, transparent) 100%);
	}

	.rd2-copy {
		padding: 38px 22px;
	}

	.rd2-root h1 {
		font-size: clamp(36px, 11vw, 48px);
	}

	.rd2-benefits {
		grid-template-columns: 1fr;
		gap: 10px;
		margin-top: 28px;
	}

	.rd2-benefit {
		margin-left: 0;
		padding: 2px 14px;
	}

	.rd2-benefit-text {
		display: none;
	}

	.rd2-action {
		padding: 28px 16px;
	}

	.rd2-card {
		padding: 28px 22px;
	}

	.rd2-faq {
		padding: 58px 20px;
	}

	.rd2-root summary {
		font-size: 16px;
	}
}

@media (prefers-reduced-motion: reduce) {
	.rd2-root *,
	.rd2-root *::before,
	.rd2-root *::after {
		scroll-behavior: auto !important;
		transition: none !important;
	}
}
</style>
