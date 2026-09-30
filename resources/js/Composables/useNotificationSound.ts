import axios from "axios"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { computed, ref, watch } from "vue"
import { openChatPane } from "@/Composables/useChatPane"
import { useLayoutStore } from "@/Stores/layout"
import { ctrans } from "@/Composables/useTrans"
import { useWhatsappCall, type WhatsappCall } from "@/Composables/useWhatsappCall"

export type NotificationSoundOptions = {
	frequency?: number
	volume?: number
	duration?: number
	type?: OscillatorType
}

let audioCtx: AudioContext | null = null
let audioEl: HTMLAudioElement | null = null

export const playNotificationSound = async (opts: NotificationSoundOptions = {}) => {
	const frequency = opts.frequency ?? 880
	const volume = opts.volume ?? 0.08
	const duration = opts.duration ?? 100
	const type = opts.type ?? "sine"
	try {
		const Ctx: any = (window as any).AudioContext || (window as any).webkitAudioContext
		if (!Ctx) return
		if (!audioCtx) {
			audioCtx = new Ctx()
		}
		if ((audioCtx as any).state === "suspended") {
			try {
				await (audioCtx as any).resume()
			} catch { }
		}
		const oscillator = audioCtx.createOscillator()
		const gain = audioCtx.createGain()
		oscillator.type = type
		oscillator.frequency.value = frequency
		gain.gain.value = volume
		oscillator.connect(gain)
		gain.connect(audioCtx.destination)
		oscillator.start()
		setTimeout(() => {
			try {
				oscillator.stop()
			} catch { }
		}, duration)
	} catch { }
}

export const setNotificationSoundUrl = (url: string) => {
	try {
		audioEl = new Audio(url)
		audioEl.preload = "auto"
	} catch { }
}

export const playNotificationSoundFile = async (url?: string) => {
	try {
		if (url) {
			setNotificationSoundUrl(url)
		}
		if (!audioEl) return
		audioEl.currentTime = 0
		await audioEl.play()
	} catch {
		await playNotificationSound()
	}
}

export const buildStorageUrl = (fileName: string, baseUrl?: string) => {
	if (!fileName) return ""
	const isAbsolute = /^https?:\/\//i.test(fileName)
	if (isAbsolute) return fileName
	const prefix = (baseUrl || "").replace(/\/+$/, "")
	const path = fileName.replace(/^\/+/, "")
	return `${prefix}/assets/${path}`
}

export const totalUnread = ref(0)
export const assignedUnread = ref(0)
export const unassignedUnread = ref(0)

type WaitingSpan = { sessions: number; oldest_at: string | null; url: string | null }
type Waiting = WaitingSpan & { live: WaitingSpan }
const nobodyWaiting = (): Waiting => ({ sessions: 0, oldest_at: null, url: null, live: { sessions: 0, oldest_at: null, url: null } })

export const chatsWaiting = ref<Waiting>(nobodyWaiting())
export const emailsWaiting = ref<Waiting>(nobodyWaiting())
export const customerPeek = ref<{ key: string; channel: string; isEmail: boolean; sender: string; subject: string | null; text: string; url: string | null } | null>(null)
export const desktopAlerts = ref<NotificationPermission | "unsupported">(typeof Notification === "undefined" ? "unsupported" : Notification.permission)

const readStorage = (key: string) => {
	try {
		return JSON.parse(localStorage.getItem(key) ?? "null")
	} catch {
		return null
	}
}

const writeStorage = (key: string, value: unknown) => {
	try {
		localStorage.setItem(key, JSON.stringify(value))
	} catch { }
}

const withTabLock = <T>(name: string, task: () => Promise<T>): Promise<T> => {
	const locks = (navigator as any)?.locks
	return locks ? locks.request(name, task) : task()
}

const summaryKey = (userId: number) => `aiku-chat-summary-${userId}`

const applySummary = (data: any) => {
	assignedUnread.value = data?.assigned_unread_count ?? 0
	unassignedUnread.value = data?.unassigned_unread_count ?? 0
	totalUnread.value = data?.total_unread_count ?? 0
	chatsWaiting.value = data?.waiting?.chat ?? nobodyWaiting()
	emailsWaiting.value = data?.waiting?.email ?? nobodyWaiting()
}

const fetchUnreadCount = async (
	baseUrl: string,
	activeTab: string,
	myAgentId: number
) => {
	try {
		const res = await axios.get(
			`${baseUrl}/app/api/chats/users/${myAgentId}/unread-messages`
		)

		const data = res.data?.data
		if (!data) return

		applySummary(data)
		writeStorage(summaryKey(myAgentId), { at: Date.now(), data })

	} catch (e) {
		if (e?.response?.status === 403) {
			return
		}

		console.error("Failed to fetch unread count!", e?.response?.data?.message || e)
	}
}

/**
 * Every open aiku tab hears the same chat event: they queue on one lock, the first asks the
 * server and the rest take its answer.
 */
const refreshSharedSummary = (baseUrl: string, myAgentId: number, maxAgeMs: number) =>
	withTabLock("aiku-chat-summary", async () => {
		const cached = readStorage(summaryKey(myAgentId))
		if (cached && Date.now() - cached.at < maxAgeMs) {
			applySummary(cached.data)
			return
		}
		await fetchUnreadCount(baseUrl, "", myAgentId)
	})

export const resetUnread = () => {
	assignedUnread.value = 0
	unassignedUnread.value = 0
	totalUnread.value = 0
	chatsWaiting.value = nobodyWaiting()
	emailsWaiting.value = nobodyWaiting()
}

export const ALERT_SOUNDS = ["chime", "bells", "dingdong", "pop", "marimba", "submarine", "voice", "bird", "boing", "fart", "silent"] as const
export type AlertSound = typeof ALERT_SOUNDS[number]
export type AlertSoundKind = "chat" | "whatsapp" | "email" | "colleague" | "waiting"

const DEFAULT_ALERT_SOUNDS: Record<Exclude<AlertSoundKind, "waiting">, AlertSound> = { chat: "chime", whatsapp: "pop", email: "dingdong", colleague: "marimba" }

export const alertSoundLabels = (): Record<AlertSound, string> => ({
	chime: ctrans("Chime"),
	bells: ctrans("Bells"),
	dingdong: ctrans("Ding dong"),
	pop: ctrans("Pop pop"),
	marimba: ctrans("Marimba"),
	submarine: ctrans("Submarine sonar"),
	voice: ctrans("A voice: you have a message"),
	bird: ctrans("Angry bird"),
	boing: ctrans("Boing"),
	fart: ctrans("Fart"),
	silent: ctrans("Silent"),
})

const alertSoundsKey = (userId: number) => `aiku-alert-sounds-${userId}`

const currentAlertSounds = (): Partial<Record<AlertSoundKind, AlertSound>> | null => {
	const user = useLayoutStore().user
	return (user?.id ? readStorage(alertSoundsKey(user.id)) : null) ?? user?.settings?.alert_sounds ?? null
}

export const chosenAlertSound = (kind: AlertSoundKind): AlertSound =>
	currentAlertSounds()?.[kind] ?? (kind === "waiting" ? chosenAlertSound("chat") : DEFAULT_ALERT_SOUNDS[kind])

type Alert = {
	key: string
	title: string
	body: string
	tag: string
	sound: AlertSound
	spoken?: string
	escalation?: "louder" | "alarm"
	url?: string | null
	onOpen?: () => void
	sticky?: boolean
	play?: () => Promise<boolean>
}

const ALERTS_KEY = "aiku-alerts"
const FRONT_KEY = "aiku-front"

const readFront = (): { at: number; tab: string; open: string[] } | null => {
	const front = readStorage(FRONT_KEY)
	return front && Date.now() - front.at < 7000 ? front : null
}

export const aikuInFront = () => document.hasFocus() || !!readFront()

export const isOpenInFront = (ulid: string) => !!readFront()?.open?.includes(ulid)

const withTimeout = <T>(promise: Promise<T>, ms: number) =>
	Promise.race([promise, new Promise<never>((_, reject) => setTimeout(() => reject(new Error("timeout")), ms))])

type Tone = [frequency: number, ms: number, endFrequency?: number]

type ToneShape = { gapMs?: number, hold?: number }

const playTones = async (tones: Tone[], volume: number, type: OscillatorType, ringFor = 1, shape: ToneShape = {}): Promise<boolean> => {
	const Ctx: any = (window as any).AudioContext || (window as any).webkitAudioContext
	if (!Ctx) return false
	audioCtx ??= new Ctx()
	const ctx = audioCtx as AudioContext
	if (ctx.state === "suspended") {
		await withTimeout(ctx.resume(), 300).catch(() => { })
	}
	if (ctx.state !== "running") return false
	let startAt = ctx.currentTime
	for (const [frequency, ms, endFrequency] of tones) {
		const endAt = startAt + (ms * ringFor) / 1000
		const oscillator = ctx.createOscillator()
		const gain = ctx.createGain()
		oscillator.type = type
		oscillator.frequency.setValueAtTime(frequency, startAt)
		if (endFrequency) oscillator.frequency.exponentialRampToValueAtTime(endFrequency, startAt + ms / 1000)
		if (shape.hold) {
			gain.gain.setValueAtTime(0.0001, startAt)
			gain.gain.linearRampToValueAtTime(volume, startAt + 0.005)
			gain.gain.setValueAtTime(volume, startAt + (ms * shape.hold) / 1000)
		} else {
			gain.gain.setValueAtTime(volume, startAt)
		}
		gain.gain.exponentialRampToValueAtTime(0.0001, endAt)
		oscillator.connect(gain).connect(ctx.destination)
		oscillator.start(startAt)
		oscillator.stop(endAt)
		startAt += (ms + (shape.gapMs ?? 70)) / 1000
	}
	return true
}

const playFile = async (volume: number, fileName = "notification.mp3"): Promise<boolean> => {
	try {
		const audio = new Audio(buildStorageUrl(`sound/${fileName}`, useLayoutStore().appUrl))
		audio.volume = volume
		await withTimeout(audio.play(), 2000)
		return true
	} catch {
		return false
	}
}

const speak = (text: string, voice: { lang?: string, pitch?: number, rate?: number, volume?: number } = {}) => new Promise<boolean>((resolve) => {
	const synth = window.speechSynthesis
	if (!synth || !text) return resolve(false)
	const utterance = new SpeechSynthesisUtterance(text)
	utterance.lang = voice.lang ?? (document.documentElement.lang || navigator.language)
	utterance.pitch = voice.pitch ?? 1
	utterance.rate = voice.rate ?? 1
	utterance.volume = voice.volume ?? 1
	utterance.onstart = () => resolve(true)
	utterance.onerror = () => resolve(false)
	synth.speak(utterance)
	setTimeout(() => resolve(false), 3000)
})

export const playChosenSound = (sound: AlertSound, spoken = ""): Promise<boolean> => {
	switch (sound) {
		case "silent": return Promise.resolve(true)
		case "chime": return playFile(1)
		case "bells": return playTones([[1318, 220], [1760, 220], [1318, 380]], 0.2, "sine", 3)
		case "dingdong": return playTones([[880, 180], [660, 320]], 0.16, "sine", 2)
		case "pop": return playTones([[1175, 70], [1568, 120]], 0.2, "triangle")
		case "marimba": return playTones([[523, 110], [659, 110], [784, 220]], 0.25, "triangle", 2)
		case "submarine": return playTones([[1150, 500], [1150, 500]], 0.22, "sine", 3)
		case "voice": return speak(spoken || ctrans("You have a message"))
		case "bird": return playTones([[1900, 90, 3200], [2100, 90, 3400], [1700, 160, 3000]], 0.15, "triangle")
		case "boing": return playTones([[160, 380, 820]], 0.3, "triangle")
		case "fart": return playTones([[150, 380, 55], [110, 260, 45]], 0.35, "sawtooth")
	}
}

const playAlertSound = async (alert: Alert): Promise<boolean> => {
	if (alert.play) {
		return alert.play()
	}
	if (alert.escalation === "alarm") {
		return playTones([[880, 220], [620, 220], [880, 220], [620, 220], [880, 220], [620, 220]], 0.25, "square")
	}
	const played = await playChosenSound(alert.sound, alert.spoken)
	if (played && alert.escalation === "louder") {
		await playTones([[1180, 160], [1180, 160]], 0.2, "square")
	}
	return played
}

const showDesktopAlert = (alert: Alert) => {
	if (desktopAlerts.value !== "granted" || aikuInFront()) return
	try {
		const notification = new Notification(alert.title, { body: alert.body, tag: alert.tag, renotify: true, requireInteraction: !!alert.sticky } as NotificationOptions)
		notification.onclick = () => {
			window.focus()
			notification.close()
			if (alert.onOpen) {
				alert.onOpen()
			} else if (alert.url) {
				openChatPane(alert.url)
			}
		}
	} catch { }
}

/**
 * Staff keep many aiku tabs open: the tabs take turns on one lock, so each alert pops up once
 * and rings once, and a tab the browser will not let play sound leaves the ring to the next.
 */
export const alertOnce = (alert: Alert) =>
	withTabLock(ALERTS_KEY, async () => {
		const handled: Record<string, number> = readStorage(ALERTS_KEY) ?? {}
		const now = Date.now()
		for (const key of Object.keys(handled)) {
			if (now - handled[key] > 15 * 60_000) delete handled[key]
		}
		if (!handled[`popup:${alert.key}`]) {
			showDesktopAlert(alert)
			handled[`popup:${alert.key}`] = now
		}
		if (!handled[`sound:${alert.key}`] && await playAlertSound(alert)) {
			handled[`sound:${alert.key}`] = now
		}
		writeStorage(ALERTS_KEY, handled)
	})

export const enableDesktopAlerts = async () => {
	if (typeof Notification === "undefined") return
	desktopAlerts.value = await Notification.requestPermission()
}

export const waitedFor = (since: string | null, now = Date.now()) => {
	if (!since) return ""
	const minutes = Math.floor((now - Date.parse(since)) / 60_000)
	if (minutes < 1) return "<1m"
	return minutes < 60 ? `${minutes}m` : `${Math.floor(minutes / 60)}h`
}

const ESCALATE_EVERY = 30_000

const channelLabel = (channel: string | null) =>
	channel === "email" ? ctrans("Email") : channel === "whatsapp" ? ctrans("WhatsApp") : ctrans("Chat")

type StaffAlertSource = {
	alertingUnread: number
	fullViewUlid: string | null
	openWindows: { ulid: string; minimised: boolean }[]
}

let started = false

/**
 * One home for everything that tells staff somebody is waiting, loaded with the layout so it
 * works whichever size the bar is: the customer chat list, the escalation while a chat or
 * WhatsApp stays unanswered, and the count in the browser tab.
 */
export const startWorkAlerts = (staff: StaffAlertSource) => {
	if (started || typeof window === "undefined") return
	started = true

	const layout = useLayoutStore()
	const myId: number | undefined = layout.user?.id
	const baseUrl = layout.appUrl ?? ""
	const tabId = Math.random().toString(36).slice(2)

	if (myId) {
		watch(() => layout.user?.settings?.alert_sounds, (sounds) => writeStorage(alertSoundsKey(myId), sounds ?? null), { immediate: true, deep: true })
	}

	const openUlids = () => [
		...staff.openWindows.filter((openWindow) => !openWindow.minimised).map((openWindow) => openWindow.ulid),
		...(staff.fullViewUlid ? [staff.fullViewUlid] : []),
	]
	const markFront = () => {
		if (document.hasFocus()) writeStorage(FRONT_KEY, { at: Date.now(), tab: tabId, open: openUlids() })
	}
	window.addEventListener("focus", () => {
		markFront()
		if (typeof Notification !== "undefined") desktopAlerts.value = Notification.permission
	})
	window.addEventListener("blur", () => {
		if (readStorage(FRONT_KEY)?.tab === tabId) writeStorage(FRONT_KEY, null)
	})
	setInterval(markFront, 3000)
	watch(openUlids, markFront)

	const titleCount = computed(() => chatsWaiting.value.live.sessions + emailsWaiting.value.live.sessions + staff.alertingUnread)
	const applyTitle = () => {
		const base = document.title.replace(/^\(\d+\)\s/, "")
		const title = titleCount.value > 0 ? `(${titleCount.value}) ${base}` : base
		if (document.title !== title) document.title = title
	}
	watch(titleCount, applyTitle, { immediate: true })
	new MutationObserver(applyTitle).observe(document.head, { childList: true, subtree: true, characterData: true })

	const { applyBroadcast: applyCallBroadcast } = useWhatsappCall()
	const onCallEvent = (call: WhatsappCall) => {
		if (!call?.id) return
		applyCallBroadcast(call, call.organisation ?? undefined)

		if (call.direction !== "user_initiated") return

		if (call.status === "in_progress" && call.user_id !== myId) {
			alertOnce({
				key: `whatsapp-call-answered:${call.id}`,
				title: ctrans("WhatsApp call answered"),
				body: ctrans(":name is handling the call from :phone", {
					name: call.user_name ?? ctrans("Another customer service"),
					phone: call.phone_number ?? "",
				}),
				tag: `whatsapp-call-${call.id}`,
				sound: "silent",
			})
			return
		}

		if (call.status !== "ringing") return
		alertOnce({
			key: `whatsapp-call:${call.id}`,
			title: ctrans("Incoming WhatsApp call"),
			body: call.phone_number ?? "",
			tag: `whatsapp-call-${call.id}`,
			sound: chosenAlertSound("whatsapp"),
			spoken: ctrans("Incoming WhatsApp call"),
			sticky: true,
		})
	}

	const customerServiceShopIds: number[] = Array.isArray(layout.user?.customer_service_shops) ? layout.user.customer_service_shops : []
	const whenEchoReady = (callback: () => void) => {
		const echoReady = setInterval(() => {
			if ((window as any).Echo?.connector?.pusher) {
				clearInterval(echoReady)
				callback()
			}
		}, 300)
	}
	whenEchoReady(() => customerServiceShopIds.forEach((shopId) => window.Echo.private(`whatsapp-calls.${shopId}`).listen(".call", onCallEvent)))

	if (!layout.user?.is_agent || !myId) return

	window.addEventListener("storage", (event) => {
		if (event.key === summaryKey(myId) && event.newValue) applySummary(JSON.parse(event.newValue).data)
	})
	const refresh = (maxAgeMs: number) => refreshSharedSummary(baseUrl, myId, maxAgeMs)
	refresh(25_000)
	setInterval(() => refresh(25_000), 30_000)

	const onChatListEvent = (event: any) => {
		refresh(3000)

		const message = event?.message
		if (!message || !["guest", "user"].includes(message.sender_type) || message.is_spam) return
		if (message.assigned_user_id && message.assigned_user_id !== myId) return

		customerPeek.value = {
			key: `${message.channel}:${message.id}`,
			channel: channelLabel(message.channel),
			isEmail: message.channel === "email",
			sender: message.sender_name,
			subject: message.subject ?? null,
			text: message.text ?? "",
			url: event.session?.url ?? null,
		}

		alertOnce({
			key: `${message.channel}:${message.id}`,
			title: `${channelLabel(message.channel)} · ${message.sender_name}`,
			body: message.text ?? "",
			tag: `customer-${event.session?.ulid}`,
			url: event.session?.url,
			sound: chosenAlertSound(message.channel === "email" ? "email" : (message.channel === "whatsapp" ? "whatsapp" : "chat")),
			spoken: ctrans("You have a message from :name", { name: message.sender_name }),
		})
	}

	whenEchoReady(() => {
		const shopIds: number[] = Array.isArray(layout.user?.agent_shops) ? layout.user.agent_shops : []
		shopIds.forEach((shopId) => {
			window.Echo.join(`chat-list.${shopId}`)
				.listen(".chatlist", onChatListEvent)
				.listen(".meta-chatlist", onChatListEvent)
		})
	})

	/**
	 * Email waits its turn. A website or WhatsApp customer who wrote in the last few minutes is
	 * watching the screen, so the alert grows every 30 seconds until somebody reads the chat,
	 * unless this person chose Silent for customers still waiting.
	 */
	const escalationStage = () => {
		const since = chatsWaiting.value.live.oldest_at
		return since ? Math.floor((Date.now() - Date.parse(since)) / ESCALATE_EVERY) : 0
	}
	const escalationKey = () => `escalate:${chatsWaiting.value.live.oldest_at}:${escalationStage()}`
	const escalateUnansweredChat = async () => {
		if (chosenAlertSound("waiting") === "silent" || escalationStage() < 1 || readStorage(ALERTS_KEY)?.[`popup:${escalationKey()}`]) return
		await refresh(10_000)
		const stage = escalationStage()
		if (stage < 1) return
		alertOnce({
			key: escalationKey(),
			title: ctrans("Customer waiting"),
			body: ctrans(":count waiting, the longest for :wait", { count: chatsWaiting.value.live.sessions, wait: waitedFor(chatsWaiting.value.live.oldest_at) }),
			tag: "customers-waiting",
			url: chatsWaiting.value.live.url,
			sound: chosenAlertSound("waiting"),
			spoken: ctrans("A customer is waiting"),
			escalation: stage === 1 ? "louder" : "alarm",
			sticky: stage > 1,
		})
	}
	setInterval(escalateUnansweredChat, 5000)
}

export const ORDER_ALERT_SOUNDS = ["till", "yeehaw", "bell", "coins", "funny", "oh_yeah"] as const
export type OrderAlertSound = typeof ORDER_ALERT_SOUNDS[number] | "silent"
export type OrderAlertPunch = 1 | 2 | 3

export const orderAlertSoundLabels = (): Record<OrderAlertSound, string> => ({
	till: ctrans("Cash till"),
	yeehaw: ctrans("Cowboy: yee-haw"),
	bell: ctrans("Bell"),
	coins: ctrans("Coins"),
	funny: ctrans("Funny"),
	oh_yeah: ctrans("Cheeky: oh yeah"),
	silent: ctrans("Silent"),
})

export const ORDER_ALERT_PUNCH: Record<string, OrderAlertPunch> = {
	ecom_small: 1,
	ecom_normal: 2,
	ecom_big: 3,
	dropshipping_unpaid: 2,
	dropshipping_first_channel_order: 3,
}

const coinTones = (count: number): Tone[] => Array.from({ length: count }, () => [2400 + Math.random() * 1800, 45])

const coins = (count: number, volume: number) => playTones(coinTones(count), volume * 0.6, "sine", 1.5, { gapMs: 25, hold: 0.2 })

export const playOrderAlertSound = async (sound: OrderAlertSound, punch: OrderAlertPunch): Promise<boolean> => {
	const volume = [0.08, 0.14, 0.2][punch - 1]
	switch (sound) {
		case "silent": return true
		case "till": return playFile([0.6, 0.9, 1][punch - 1], ["cash-register-small.mp3", "cash-register.mp3", "cash-register-big.mp3"][punch - 1])
		case "yeehaw": return playFile([0.5, 0.8, 1][punch - 1], "yeehaw.mp3")
		case "bell": return playTones(Array.from({ length: punch }, (): Tone => [1760, 280]), volume, "sine", punch + 1, { hold: 0.1 })
		case "coins": return coins(punch * 8, volume * 1.5)
		case "funny": return playTones(punch === 1 ? [[160, 300, 820]] : [[400, 160 * punch, 1800], [1800, 140 * punch, 400], [160, 380, 820]], volume, "sine", 1, { gapMs: 20, hold: 0.7 })
		case "oh_yeah": {
			const synth = window.speechSynthesis
			if (!synth) return playOrderAlertSound("till", punch)
			synth.cancel()
			speak(punch === 3 ? "Oh... yeah!" : "Oh yeah", { lang: "en-US", pitch: punch === 3 ? 0.4 : 0.6, rate: punch === 3 ? 0.6 : 0.85, volume: [0.5, 0.8, 1][punch - 1] })
			return true
		}
	}
}

const ORDER_SOUND_KEY = "aiku-order-sound"
const ORDER_SOUND_COOLDOWN = 20_000

const playOrderSoundAfterCooldown = async (sound: OrderAlertSound, punch: OrderAlertPunch): Promise<boolean> => {
	const last = readStorage(ORDER_SOUND_KEY)
	if (last && Date.now() - last.at < ORDER_SOUND_COOLDOWN && punch <= last.punch) return true
	const played = await playOrderAlertSound(sound, punch)
	if (played) writeStorage(ORDER_SOUND_KEY, { at: Date.now(), punch })
	return played
}

type NewOrderEvent = {
	order_id: number
	shop_id: number
	shop: string
	types: string[]
	reference: string
	customer: string
	amount: number
	currency: string
	is_unpaid: boolean
	url: string
}

const formatMoney = (amount: number, currency: string) => {
	try {
		return new Intl.NumberFormat(undefined, { style: "currency", currency, currencyDisplay: "narrowSymbol" }).format(amount)
	} catch {
		return `${amount} ${currency}`
	}
}

let orderAlertsStarted = false

export const startOrderAlerts = () => {
	if (orderAlertsStarted || typeof window === "undefined") return
	orderAlertsStarted = true

	const layout = useLayoutStore()
	const listening = new Set<number>()

	const onNewOrder = (event: NewOrderEvent) => {
		const preferences = layout.order_alerts
		const type = event.types
			.filter((alertType) => preferences?.shops?.[event.shop_id]?.includes(alertType))
			.sort((a, b) => (ORDER_ALERT_PUNCH[b] ?? 2) - (ORDER_ALERT_PUNCH[a] ?? 2))[0]
		if (!preferences || !type) return
		const punch = ORDER_ALERT_PUNCH[type] ?? 2

		const title = event.is_unpaid ? ctrans("Unpaid order · :shop", { shop: event.shop }) : ctrans("New order · :shop", { shop: event.shop })
		const body = `${event.reference} · ${event.customer} · ${formatMoney(event.amount, event.currency)}`

		if (preferences.popup.show && document.visibilityState === "visible") {
			notify({
				group: "order-alerts",
				title,
				text: body,
				duration: (layout.user?.settings?.alert_preview_seconds ?? 6) * 1000,
				data: { ...event, type, money: formatMoney(event.amount, event.currency), sound_blocked: (navigator as any).userActivation?.hasBeenActive === false },
			})
		}

		alertOnce({
			key: `order:${event.order_id}`,
			title,
			body,
			tag: `order-${event.order_id}`,
			sound: "silent",
			play: () => playOrderSoundAfterCooldown((preferences.sounds[type] ?? "till") as OrderAlertSound, punch),
			onOpen: () => router.visit(event.url),
		})
	}

	const listen = () => Object.keys(layout.order_alerts?.shops ?? {}).map(Number)
		.filter((shopId) => !listening.has(shopId))
		.forEach((shopId) => {
			listening.add(shopId)
			window.Echo.private(`grp.shop.${shopId}.new-orders`).listen(".new-order", onNewOrder)
		})

	const echoReady = setInterval(() => {
		if ((window as any).Echo?.connector?.pusher) {
			clearInterval(echoReady)
			listen()
			watch(() => layout.order_alerts?.shops, listen, { deep: true })
		}
	}, 300)
}
