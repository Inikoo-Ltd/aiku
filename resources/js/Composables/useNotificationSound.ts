import axios from "axios"
import { computed, ref, watch } from "vue"
import { openChatPane } from "@/Composables/useChatPane"
import { useLayoutStore } from "@/Stores/layout"
import { ctrans } from "@/Composables/useTrans"

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
export type AlertSoundKind = "chat" | "whatsapp" | "email" | "colleague"

const DEFAULT_ALERT_SOUNDS: Record<AlertSoundKind, AlertSound> = { chat: "chime", whatsapp: "pop", email: "dingdong", colleague: "marimba" }

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

export const chosenAlertSound = (kind: AlertSoundKind): AlertSound =>
	useLayoutStore().user?.settings?.alert_sounds?.[kind] ?? DEFAULT_ALERT_SOUNDS[kind]

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

const playTones = async (tones: Tone[], volume: number, type: OscillatorType, ringFor = 1): Promise<boolean> => {
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
		gain.gain.setValueAtTime(volume, startAt)
		gain.gain.exponentialRampToValueAtTime(0.0001, endAt)
		oscillator.connect(gain).connect(ctx.destination)
		oscillator.start(startAt)
		oscillator.stop(endAt)
		startAt += (ms + 70) / 1000
	}
	return true
}

const playFile = async (volume: number): Promise<boolean> => {
	try {
		const audio = new Audio(buildStorageUrl("sound/notification.mp3", useLayoutStore().appUrl))
		audio.volume = volume
		await withTimeout(audio.play(), 2000)
		return true
	} catch {
		return false
	}
}

const speak = (text: string) => new Promise<boolean>((resolve) => {
	const synth = window.speechSynthesis
	if (!synth || !text) return resolve(false)
	const utterance = new SpeechSynthesisUtterance(text)
	utterance.lang = document.documentElement.lang || navigator.language
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

	const subscribe = () => {
		const shopIds: number[] = Array.isArray(layout.user?.agent_shops) ? layout.user.agent_shops : []
		shopIds.forEach((shopId) => {
			window.Echo.join(`chat-list.${shopId}`)
				.listen(".chatlist", onChatListEvent)
				.listen(".meta-chatlist", onChatListEvent)
		})
	}
	const echoReady = setInterval(() => {
		if ((window as any).Echo?.connector?.pusher) {
			clearInterval(echoReady)
			subscribe()
		}
	}, 300)

	/**
	 * Email waits its turn. A website or WhatsApp customer who wrote in the last few minutes is
	 * watching the screen, so the alert grows every 30 seconds until somebody reads the chat.
	 */
	const escalationStage = () => {
		const since = chatsWaiting.value.live.oldest_at
		return since ? Math.floor((Date.now() - Date.parse(since)) / ESCALATE_EVERY) : 0
	}
	const escalationKey = () => `escalate:${chatsWaiting.value.live.oldest_at}:${escalationStage()}`
	const escalateUnansweredChat = async () => {
		if (escalationStage() < 1 || readStorage(ALERTS_KEY)?.[`popup:${escalationKey()}`]) return
		await refresh(10_000)
		const stage = escalationStage()
		if (stage < 1) return
		alertOnce({
			key: escalationKey(),
			title: ctrans("Customer waiting"),
			body: ctrans(":count waiting, the longest for :wait", { count: chatsWaiting.value.live.sessions, wait: waitedFor(chatsWaiting.value.live.oldest_at) }),
			tag: "customers-waiting",
			url: chatsWaiting.value.live.url,
			sound: chosenAlertSound("chat"),
			spoken: ctrans("A customer is waiting"),
			escalation: stage === 1 ? "louder" : "alarm",
			sticky: stage > 1,
		})
	}
	setInterval(escalateUnansweredChat, 5000)
}
