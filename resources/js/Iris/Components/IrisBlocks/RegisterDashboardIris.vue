<script setup lang="ts">
import { computed, inject, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { GoogleLogin } from "vue3-google-login"
import { router, usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faEnvelope, faPercent, faStore, faTag, faTruck, faUsers } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { getStyles } from "@/Composables/styles"
import { getRefRedirect } from "@/Composables/Retina/useGetRedirectUrl"

library.add(faBox, faEnvelope, faPercent, faStore, faTag, faTruck, faUsers, faWhatsapp)

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

const accentStyle = computed(() => ({
	"--rd-accent": props.fieldValue?.accent_color || "var(--theme-color-0, #c94f00)",
}))

const benefits = computed(() =>
	props.fieldValue?.hero?.show_benefits === false ? [] : (props.fieldValue?.hero?.benefits ?? [])
)

const showWhatsappNote = computed(() => {
	const whatsapp = props.fieldValue?.signup?.whatsapp

	return whatsapp?.show !== false && (props.isWorkshop || !!(whatsapp?.title || whatsapp?.text || whatsapp?.note))
})

/**
 * The WhatsApp note opens a chat with the number the shop gives, the same wa.me link the website
 * footer builds, so a visitor can ask before registering. Without a number the note stays plain text.
 */
const whatsappHref = computed(() => {
	const whatsapp = props.fieldValue?.signup?.whatsapp
	const number = String(whatsapp?.number ?? "").replace(/[^0-9]/g, "")

	if (!number) {
		return null
	}

	const message = String(whatsapp?.message ?? "").trim()

	return `https://wa.me/${number}${message ? `?text=${encodeURIComponent(message)}` : ""}`
})

const showAudienceNote = computed(() => {
	const audience = props.fieldValue?.signup?.audience

	return audience?.show !== false && (props.isWorkshop || !!audience?.text)
})

const faqItems = computed(() =>
	props.fieldValue?.faq?.show === false ? [] : (props.fieldValue?.faq?.items ?? [])
)

/**
 * An image is either uploaded through the workshop or given as an address, so a shop can point at
 * an image it already hosts. The uploaded one wins, the address is read as a plain source.
 */
const resolveImage = (image: any, url?: string | null) => {
	if (image) {
		return image
	}

	const address = typeof url === "string" ? url.trim() : ""

	return address ? { original: address } : null
}

const heroImage = computed(() => resolveImage(props.fieldValue?.hero?.image, props.fieldValue?.hero?.image_url))

const resolveLink = (link: any, fallbackHref: string) => ({
	type: link?.type ?? "internal",
	target: link?.target ?? "_self",
	href: link?.href || fallbackHref,
	canonical_url: link?.canonical_url,
})

const registerLink = computed(() => resolveLink(props.fieldValue?.signup?.button?.link, "/app/register"))

/**
 * Registration with Google follows the retina register page: the Google token is posted to the
 * login endpoint, a known customer is sent on to where they were going and anybody else continues
 * to the Google registration form with that token.
 */
const page = usePage<any>()

const googleClientId = computed(() => page?.props?.google?.client_id || layout?.iris?.google?.client_id)
const isGoogleVisible = computed(
	() => props.fieldValue?.signup?.google?.show !== false && (props.isWorkshop || !!googleClientId.value)
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
	<div :id="fieldValue?.id ? fieldValue.id : 'register-dashboard' + indexBlock" component="register-dashboard"
		class="rd-root" :class="{ 'rd-workshop': isWorkshop }" data-rd-panel="layout" :style="{
			...accentStyle,
			...getStyles(fieldValue?.container?.properties, screenType),
		}">
		<div class="rd-main">
			<section class="rd-hero" data-rd-panel="hero">
				<Image v-if="heroImage" :src="heroImage" :alt="fieldValue?.hero?.image_alt ?? ''" :imageCover="true"
					class="rd-photo" />

				<div class="rd-copy">
					<h1 v-if="fieldValue?.hero?.title || isWorkshop"
						style="font-size: 2.9rem; font-weight: bold;">
						<slot name="editable" :path="['hero', 'title']" :value="fieldValue?.hero?.title" placeholder="Title">
							<span v-html="fieldValue?.hero?.title" />
						</slot>
					</h1>

					<div class="rd-intro editor-class">
						<slot name="editable" :path="['hero', 'intro']" :value="fieldValue?.hero?.intro" placeholder="Intro">
							<div v-html="fieldValue?.hero?.intro" />
						</slot>
					</div>

					<div class="mt-8">
						<div v-if="benefits.length" class="rd-benefits">
							<div v-for="(benefit, index) in benefits" :key="index" class="rd-benefit" data-rd-panel="benefits" :data-rd-index="index">
								<span v-if="benefit.icon" class="rd-benefit-icon" aria-hidden="true">
									<FontAwesomeIcon :icon="benefit.icon" fixed-width />
								</span>
								<span class="rd-benefit-text">
									<slot name="editable" :path="['hero', 'benefits', index, 'text']" :value="benefit.text" placeholder="Benefit">
										<span v-html="benefit.text" />
									</slot>
								</span>
							</div>
						</div>
					</div>

				</div>
			</section>

			<section class="rd-signup" data-rd-panel="signup">
				<div v-if="isLoadingGoogle" class="rd-signup-loading">
					<LoadingIcon class="text-4xl" />
				</div>

				<div class="py-3">
					<h2 v-if="fieldValue?.signup?.title || isWorkshop"
						style="font-size: 2.7rem; font-weight: bold;">
						<slot name="editable" :path="['signup', 'title']" :value="fieldValue?.signup?.title" placeholder="Title">
							<span v-html="fieldValue?.signup?.title" />
						</slot>
					</h2>
					<div v-if="fieldValue?.signup?.subtitle || isWorkshop" class="rd-subtitle mt-2">
						<slot name="editable" :path="['signup', 'subtitle']" :value="fieldValue?.signup?.subtitle" placeholder="Subtitle">
							<span v-html="fieldValue?.signup?.subtitle" />
						</slot>
					</div>
				</div>


				<div v-if="fieldValue?.signup?.button?.show !== false" data-rd-panel="register-button">
					<LinkIris :href="registerLink.href"
						:type="registerLink.type" :target="registerLink.target" :canonical_url="registerLink.canonical_url"
						class="rd-primary mt-4">
						<FontAwesomeIcon v-if="fieldValue?.signup?.button?.icon" :icon="fieldValue.signup.button.icon"
							fixed-width aria-hidden="true" />
						<span class="rd-inline-text">
							<slot name="editable" :path="['signup', 'button', 'label']" :value="fieldValue?.signup?.button?.label" placeholder="Register with email">
								<span v-html="fieldValue?.signup?.button?.label || trans('Register with email')" />
							</slot>
						</span>
					</LinkIris>
				</div>

				<div v-if="isGoogleVisible" class="rd-google" data-rd-panel="google">
					<div v-if="fieldValue?.signup?.google?.note || isWorkshop" class="rd-google-note">
						<slot name="editable" :path="['signup', 'google', 'note']" :value="fieldValue?.signup?.google?.note" placeholder="Google note (optional)">
							<span v-html="fieldValue?.signup?.google?.note" />
						</slot>
					</div>

					<button v-if="isWorkshop" type="button" class="rd-google-button">
						<svg class="rd-google-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"
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
						<span class="rd-inline-text">
							<slot name="editable" :path="['signup', 'google', 'label']" :value="fieldValue?.signup?.google?.label" placeholder="Register with Google">
								<span v-html="fieldValue?.signup?.google?.label || trans('Register with Google')" />
							</slot>
						</span>
					</button>

					<GoogleLogin v-else :clientId="googleClientId" popup-type="TOKEN"
						:callback="(e: GoogleLoginResponse) => onCallbackGoogleLogin(e)">
						<template #default>
							<div class="rd-google-button">
								<svg class="rd-google-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"
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
								<span v-html="fieldValue?.signup?.google?.label || trans('Register with Google')" />
							</div>
						</template>
					</GoogleLogin>
				</div>

				<div class="rd-existing editor-class" data-rd-panel="login-note">
					<slot name="editable" :path="['signup', 'login_note']" :value="fieldValue?.signup?.login_note" placeholder="Login note">
						<div v-html="fieldValue?.signup?.login_note" />
					</slot>
				</div>

				<div v-if="showWhatsappNote || showAudienceNote" class="rd-notes">
					<component
						:is="whatsappHref ? 'a' : 'div'"
						v-if="showWhatsappNote"
						class="rd-note rd-note-whatsapp"
						data-rd-panel="whatsapp"
						:class="{ 'rd-note-link': whatsappHref }"
						:href="whatsappHref ?? undefined"
						:target="whatsappHref ? '_blank' : undefined"
						:rel="whatsappHref ? 'noopener noreferrer' : undefined">
						<span class="rd-note-icon rd-note-icon-whatsapp" aria-hidden="true">
							<FontAwesomeIcon :icon="faWhatsapp" fixed-width />
						</span>
						<div class="rd-note-body">
							<div v-if="fieldValue?.signup?.whatsapp?.title || isWorkshop" class="rd-note-title">
								<slot name="editable" :path="['signup', 'whatsapp', 'title']" :value="fieldValue?.signup?.whatsapp?.title" placeholder="WhatsApp title">
									<span v-html="fieldValue?.signup?.whatsapp?.title" />
								</slot>
							</div>
							<div v-if="fieldValue?.signup?.whatsapp?.text || isWorkshop" class="rd-note-text">
								<slot name="editable" :path="['signup', 'whatsapp', 'text']" :value="fieldValue?.signup?.whatsapp?.text" placeholder="WhatsApp text">
									<span v-html="fieldValue?.signup?.whatsapp?.text" />
								</slot>
							</div>
							<div v-if="fieldValue?.signup?.whatsapp?.note || isWorkshop" class="rd-note-small">
								<slot name="editable" :path="['signup', 'whatsapp', 'note']" :value="fieldValue?.signup?.whatsapp?.note" placeholder="WhatsApp small print">
									<span v-html="fieldValue?.signup?.whatsapp?.note" />
								</slot>
							</div>
						</div>
					</component>

					<div v-if="showAudienceNote" class="rd-note rd-note-audience" data-rd-panel="audience">
						<span v-if="fieldValue?.signup?.audience?.icon" class="rd-note-icon" aria-hidden="true">
							<FontAwesomeIcon :icon="fieldValue.signup.audience.icon" fixed-width />
						</span>
						<div class="rd-note-body">
							<div class="rd-note-text">
								<slot name="editable" :path="['signup', 'audience', 'text']" :value="fieldValue?.signup?.audience?.text" placeholder="Audience">
									<span v-html="fieldValue?.signup?.audience?.text" />
								</slot>
							</div>
						</div>
					</div>
				</div>
			</section>
		</div>

		<section v-if="faqItems.length || (isWorkshop && fieldValue?.faq?.show !== false)" class="rd-faq" data-rd-panel="faq">
			<h2 v-if="fieldValue?.faq?.title || isWorkshop">
				<slot name="editable" :path="['faq', 'title']" :value="fieldValue?.faq?.title" placeholder="FAQ title">
					<span v-html="fieldValue?.faq?.title" />
				</slot>
			</h2>

			<div v-if="isWorkshop && !faqItems.length" class="rd-faq-empty" data-rd-panel="faq-items">
				{{ trans("No questions yet. Add them in FAQ questions.") }}
			</div>

			<div v-if="faqItems.length" class="rd-faq-grid">
				<details v-for="(item, index) in faqItems" :key="index" open data-rd-panel="faq-items" :data-rd-index="index">
					<summary @click.capture="isWorkshop && $event.preventDefault()">
						<slot name="editable" :path="['faq', 'items', index, 'question']" :value="item.question" placeholder="Question">
							<span v-html="item.question" />
						</slot>
					</summary>
					<div class="rd-faq-answer editor-class">
						<slot name="editable" :path="['faq', 'items', index, 'answer']" :value="item.answer" placeholder="Answer">
							<div v-html="item.answer" />
						</slot>
					</div>
				</details>
			</div>
		</section>

	</div>
</template>

<style>
.rd-root {
	--rd-ink: #19222b;
	color: var(--rd-ink);
	font: 16px/1.5 Arial, Helvetica, sans-serif;
	width: 100%;
	margin: 0;
	isolation: isolate;
}

.rd-root *,
.rd-root *::before,
.rd-root *::after {
	box-sizing: border-box;
}

.rd-root h1,
.rd-root h2,
.rd-root p {
	margin: 0;
}

.rd-root a {
	color: inherit;
	text-decoration: none;
}

.rd-root .editor-class,
.rd-root .editor-class p,
.rd-root .editor-class li,
.rd-benefit-text {
	line-height: inherit;
	font-size: 0.8rem;
}

.rd-root h1 p,
.rd-root h2 p,
.rd-subtitle p,
.rd-inline-text p,
.rd-google-note p,
.rd-benefit-text p,
.rd-note-body p,
.rd-root summary p {
	display: inline;
}

.rd-root .rd-editable,
.rd-root .rd-editable .editor-class,
.rd-root .rd-editable .editor-class p {
	font-size: inherit;
	font-weight: inherit;
	font-family: inherit;
	line-height: inherit;
	letter-spacing: inherit;
	color: inherit;
	text-align: inherit;
	text-shadow: none;
}

.rd-root a:focus-visible,
.rd-root summary:focus-visible {
	outline: 3px solid #176b9c;
	outline-offset: 5px;
}






.rd-main {
	display: grid;
	grid-template-columns: 60% 40%;
}

.rd-hero {
	position: relative;
	min-height: 650px;
	overflow: hidden;
	background: #efe8dc;
	isolation: isolate;
}

.rd-photo {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	z-index: -1;
}

.rd-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center;
}

.rd-copy {
	padding: 42px 0 0 8.5%;
	width: 73%;
}

.rd-root h1 {
	font: 700 clamp(36px, 4.15vw, 64px) / 1.04 Georgia, "Times New Roman", serif;
	letter-spacing: -2px;
	margin-bottom: 17px;
	line-height: 1.04;
}

.rd-intro {
	font-size: clamp(17px, 1.5vw, 22px);
	line-height: 1.4;
}

.rd-benefits {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	row-gap: 20px;
	column-gap: 0;
	padding: 0 0 0 1px;
	list-style: none;
	width: 100%;
}

.rd-benefit {
	display: flex;
	align-items: center;
	gap: 12px;
	min-height: 48px;
	margin-left: -1px;
	padding: 4px 16px;
	border-left: 1px solid #c8c6c2;
	border-right: 1px solid #c8c6c2;
	font-size: 14px;
	font-weight: 600;
	line-height: 1.3;
}

.rd-benefit-icon {
	flex: 0 0 40px;
	width: 40px;
	height: 40px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 9999px;
	background: color-mix(in srgb, var(--rd-accent) 12%, white);
	color: var(--rd-accent);
	font-size: 18px;
}

.rd-benefit-text {
	min-width: 0;
}

.rd-signup {
	position: relative;
	padding: 65px 12% 32px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	background: #fff;
}

.rd-root h2 {
	font: 700 clamp(32px, 3.5vw, 52px) / 1.06 Georgia, "Times New Roman", serif;
	letter-spacing: -1.5px;
	line-height: 1.06;
}

.rd-subtitle {
	font-size: 20px;
	line-height: 1.35;
	margin: 22px 0 20px;
}

.rd-primary {
	background: var(--rd-accent);
	color: #fff;
	min-height: 56px;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 14px;
	padding: 13px 18px;
	border-radius: 5px;
	font-size: 18px;
	font-weight: 600;
	text-align: center;
	transition: filter 0.15s;
	line-height: 1.5;
}

.rd-primary:hover {
	filter: brightness(0.86);
}

.rd-primary svg {
	width: 22px;
	height: 22px;
	flex-basis: 22px;
	font-size: 20px;
	color: inherit;
}

.rd-google {
	margin-top: 22px;
}

.rd-google .g-btn-wrapper {
	width: 100%;
}

.rd-signup-loading {
	position: absolute;
	inset: 0;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(0, 0, 0, 0.5);
	color: #fff;
}

.rd-google-note {
	text-align: center;
	font-size: 15px;
	margin-bottom: 10px;
	line-height: 1.5;
}

.rd-google-button {
	position: relative;
	width: 100%;
	min-height: 52px;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	padding: 12px 18px;
	border: 1px solid #1d252e;
	border-radius: 5px;
	background: #fff;
	color: #1d252e;
	font-size: 17px;
	cursor: pointer;
	transition: background 0.15s;
	line-height: 1.5;
}

.rd-google-button:hover {
	background: #f4f4f5;
}

.rd-google-button:disabled {
	opacity: 0.7;
	cursor: default;
}

.rd-google-logo {
	position: absolute;
	left: 16px;
	width: 20px;
	height: 20px;
}

.rd-existing {
	text-align: center;
	font-size: 16px;
	margin: 27px 0;
	line-height: 1.5;
}

.rd-existing a {
	color: var(--rd-accent);
	text-decoration: underline;
	text-underline-offset: 3px;
}

.rd-notes {
	display: flex;
	flex-direction: column;
	gap: 16px;
	margin-top: 8px;
	padding-top: 22px;
	border-top: 1px solid #e5e2dd;
}

.rd-note {
	display: flex;
	align-items: flex-start;
	gap: 18px;
}

.rd-note-link {
	cursor: pointer;
}

.rd-note-link:hover .rd-note-title,
.rd-note-link:focus-visible .rd-note-title {
	color: #21b95b;
	text-decoration: underline;
	text-underline-offset: 3px;
}

.rd-note-audience {
	align-items: center;
	gap: 16px;
}

.rd-note-icon {
	flex: 0 0 37px;
	width: 37px;
	height: 37px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--rd-accent);
	font-size: 28px;
}

.rd-note-audience .rd-note-icon {
	flex-basis: 28px;
	width: 28px;
	height: 28px;
	font-size: 22px;
	filter: grayscale(1);
}

.rd-note-icon-whatsapp {
	color: #21b95b;
}

.rd-note-body {
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.rd-note-title {
	font-size: 15px;
	font-weight: 600;
	line-height: 1.35;
	color: var(--rd-ink);
}

.rd-note-text {
	font-size: 14px;
	line-height: 1.45;
	color: #3f4a55;
}

.rd-note-small {
	font-size: 13px;
	line-height: 1.45;
	color: #6b7280;
}

.rd-faq {
	padding: 42px max(6%, 24px);
	background: #fcfbf9;
}

.rd-faq h2 {
	text-align: center;
	font-size: 35px;
	margin-bottom: 25px;
	letter-spacing: -1px;
}

.rd-faq-grid {
	max-width: 1000px;
	margin: auto;
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 55px;
}

.rd-root summary {
	cursor: pointer;
	font-weight: 600;
	padding: 8px 0;
	line-height: 1.5;
}

.rd-faq-answer {
	font-size: 15px;
	line-height: 1.65;
	padding: 5px 0 10px;
	color: #444e58;
}

.rd-workshop summary .rd-editable,
.rd-workshop summary .rd-editable div,
.rd-workshop summary .rd-editable p {
	display: inline;
}

.rd-faq-empty {
	max-width: 1000px;
	margin: 0 auto;
	padding: 18px;
	border: 1px dashed #d6d3cd;
	border-radius: 8px;
	text-align: center;
	font-size: 14px;
	color: #6b7280;
	cursor: pointer;
}




@media (min-width: 1600px) {
	.rd-main {
		max-width: 1800px;
		margin: auto;
	}
}

@media (max-width: 1100px) {
	.rd-copy {
		width: 78%;
		padding-left: 6%;
		padding-top: 35px;
	}

	.rd-benefits {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.rd-signup {
		padding: 45px 8%;
	}

	.rd-hero {
		min-height: 620px;
	}
}

@media (max-width: 760px) {




	.rd-main {
		display: flex;
		flex-direction: column;
	}

	.rd-hero {
		min-height: 0;
		padding-bottom: 160px;
	}

	.rd-photo {
		top: auto;
		bottom: 0;
		height: 160px;
	}

	.rd-photo img {
		object-position: center 80%;
	}

	.rd-copy {
		width: 100%;
		background: #f7f2eb;
		padding: 24px 20px;
	}

	.rd-root h1 {
		font-size: 36px;
		letter-spacing: -1.3px;
	}

	.rd-intro {
		font-size: 16px;
	}

	.rd-benefits {
		row-gap: 16px;
		margin-top: 22px;
	}

	.rd-benefit {
		gap: 10px;
		min-height: 44px;
		padding: 2px 12px;
		font-size: 13px;
	}

	.rd-benefit-icon {
		flex-basis: 34px;
		width: 34px;
		height: 34px;
		font-size: 15px;
	}

	.rd-signup {
		padding: 30px 24px;
	}

	.rd-root h2 {
		font-size: 36px;
	}

	.rd-subtitle {
		font-size: 18px;
		margin: 16px 0 20px;
	}

	.rd-faq {
		padding: 30px 24px;
	}

	.rd-faq h2 {
		font-size: 29px;
	}

	.rd-faq-grid {
		grid-template-columns: 1fr;
		gap: 12px;
	}

	.rd-notes {
		gap: 12px;
		padding-top: 18px;
	}

	.rd-note {
		gap: 14px;
	}

	.rd-note-icon {
		flex-basis: 28px;
		width: 28px;
		height: 28px;
		font-size: 22px;
	}
}

@media (max-width: 420px) {
	.rd-benefit {
		flex-direction: column;
		justify-content: center;
		text-align: center;
		gap: 8px;
		min-height: 0;
	}
}

@media (prefers-reduced-motion: reduce) {
	.rd-root * {
		transition: none;
	}
}
</style>
