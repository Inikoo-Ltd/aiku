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
			} catch {}
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
			} catch {}
		}, duration)
	} catch {}
}

export const setNotificationSoundUrl = (url: string) => {
	try {
		audioEl = new Audio(url)
		audioEl.preload = "auto"
	} catch {}
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
	return `${prefix}/storage/${path}`
}
