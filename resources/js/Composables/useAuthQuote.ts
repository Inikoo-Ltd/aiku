export interface AuthQuote {
	text: string
	englishTranslation: string
	language: string
	languageCode: string
	direction: "ltr" | "rtl"
}

const englishLines = [
	"ship it before the light goes.",
	"a box packed well is a promise kept.",
	"every shelf remembers what it held.",
	"count twice, carry once.",
	"the day is a queue; clear it kindly.",
	"small things, sent far, arrive as big things.",
	"paper, ink, and a plan. then coffee.",
	"stock moves; so do we.",
	"what is picked is half-way home.",
	"a good label is a love letter to a stranger.",
	"the warehouse is quiet before it is busy. enjoy the quiet.",
	"the order was placed by a person. pack it like one.",
	"slow is smooth, smooth is fast.",
	"numbers are stories that agreed to behave.",
	"make the next line on the list easier than this one.",
	"invoices are memories with totals.",
	"the van leaves at four. the van always leaves at four.",
	"a barcode is a tiny poem only the scanner can read.",
	"today: fewer errors, more tea.",
	"leave the bay tidier than you found it.",
	"one source of truth, many stories.",
	"measure the pallet, not the mood.",
	"tomorrow's dispatch starts with today's put-away.",
	"kind words, clean data.",
	"somewhere a customer just smiled at a parcel. that was you.",
	"the spreadsheet is not the business. the business is the business.",
	"be the person who updates the stock count.",
	"even the returns teach us something.",
	"a calm dashboard is earned, not given.",
	"good morning. the inbox can wait five minutes.",
	"nothing ships itself. thank you.",
]

const seasonalEnglishLines: Record<string, string[]> = {
	xmas: [
		"every parcel is a small gift. these ones especially.",
		"the busiest week, the kindest boxes.",
		"tape, tinsel, and a tidy pick list.",
		"deck the shelves. then empty them.",
		"december: where the year goes out in boxes.",
	],
	newyear: [
		"new year, same stock count. let's make it right.",
		"fresh ledger. fresh coffee.",
		"january is for put-away and promises.",
	],
	winter: [
		"cold hands, warm dispatch.",
		"short days, long lists. we've got this.",
		"the van still leaves at four, even in the dark.",
	],
	spring: [
		"spring: the catalogue wakes up.",
		"new season, new families, same care.",
		"open the doors. let the returns air out.",
	],
	summer: [
		"summer: pack light, pack right.",
		"the warehouse is warm. the water is where it should be.",
		"long days, short queues. keep them short.",
		"sunscreen for you, bubble wrap for them.",
	],
	autumn: [
		"autumn: stock up, slow down, then don't.",
		"leaves fall; delivery notes shouldn't.",
		"the big season is coming. the shelves know.",
	],
}

export const translatedAuthQuotes: AuthQuote[] = [
	{
		text: "천 리 길도 한 걸음부터.",
		englishTranslation: "Even a journey of a thousand miles begins with a single step.",
		language: "한국어",
		languageCode: "ko",
		direction: "ltr",
	},
	{
		text: "七転び八起き。",
		englishTranslation: "Fall seven times, stand up eight.",
		language: "日本語",
		languageCode: "ja",
		direction: "ltr",
	},
	{
		text: "Без труда нема плода.",
		englishTranslation: "Without work, there is no reward.",
		language: "Українська",
		languageCode: "uk",
		direction: "ltr",
	},
	{
		text: "رحلة الألف ميل تبدأ بخطوة واحدة.",
		englishTranslation: "A journey of a thousand miles begins with a single step.",
		language: "العربية",
		languageCode: "ar",
		direction: "rtl",
	},
	{
		text: "Κάθε αρχή και δύσκολη.",
		englishTranslation: "Every beginning is difficult.",
		language: "Ελληνικά",
		languageCode: "el",
		direction: "ltr",
	},
	{
		text: "ძალა ერთობაშია.",
		englishTranslation: "Strength is in unity.",
		language: "ქართული",
		languageCode: "ka",
		direction: "ltr",
	},
	{
		text: "Մի ձեռքը ծափ չի տա։",
		englishTranslation: "One hand cannot clap.",
		language: "Հայերեն",
		languageCode: "hy",
		direction: "ltr",
	},
	{
		text: "जहाँ चाह, वहाँ राह।",
		englishTranslation: "Where there is a will, there is a way.",
		language: "हिन्दी",
		languageCode: "hi",
		direction: "ltr",
	},
	{
		text: "ความพยายามอยู่ที่ไหน ความสำเร็จอยู่ที่นั่น",
		englishTranslation: "Where there is effort, there is success.",
		language: "ไทย",
		languageCode: "th",
		direction: "ltr",
	},
	{
		text: "Haba na haba hujaza kibaba.",
		englishTranslation: "Little by little fills the measure.",
		language: "Kiswahili",
		languageCode: "sw",
		direction: "ltr",
	},
	{
		text: "He aha te mea nui o te ao? He tāngata, he tāngata, he tāngata.",
		englishTranslation:
			"What is the most important thing in the world? It is people, it is people, it is people.",
		language: "Te reo Māori",
		languageCode: "mi",
		direction: "ltr",
	},
	{
		text: "Deuparth gwaith yw ei ddechrau.",
		englishTranslation: "Starting the work is two-thirds of it.",
		language: "Cymraeg",
		languageCode: "cy",
		direction: "ltr",
	},
	{
		text: "Margt smátt gerir eitt stórt.",
		englishTranslation: "Many small things make one big thing.",
		language: "Íslenska",
		languageCode: "is",
		direction: "ltr",
	},
	{
		text: "סוף מעשה במחשבה תחילה.",
		englishTranslation: "The final deed begins with a thought.",
		language: "עברית",
		languageCode: "he",
		direction: "rtl",
	},
	{
		text: "توانا بود هر که دانا بود.",
		englishTranslation: "Whoever has knowledge has power.",
		language: "فارسی",
		languageCode: "fa",
		direction: "rtl",
	},
	{
		text: "⠎⠍⠁⠇⠇ ⠎⠞⠑⠏⠎ ⠎⠞⠊⠇⠇ ⠍⠕⠧⠑ ⠥⠎ ⠋⠕⠗⠺⠁⠗⠙⠲",
		englishTranslation: "Small steps still move us forward.",
		language: "English Braille",
		languageCode: "en-Brai",
		direction: "ltr",
	},
]

const seasonFor = (month: number, day: number): string | null => {
	if ((month === 12 && day >= 6) || (month === 1 && day <= 1)) {
		return "xmas"
	}

	if (month === 1 && day <= 10) {
		return "newyear"
	}

	if (month === 12 || month <= 2) {
		return "winter"
	}

	if (month >= 3 && month <= 5) {
		return "spring"
	}

	if (month >= 6 && month <= 8) {
		return "summer"
	}

	if (month >= 9 && month <= 11) {
		return "autumn"
	}

	return null
}

const quoteAtPosition = <T>(items: T[], position: number): T => {
	return items[Math.floor(position * items.length)]
}

export const authQuoteForRandomValue = (
	randomValue: number,
	date: Date = new Date()
): AuthQuote => {
	const weightedPosition = Math.min(Math.max(randomValue, 0), 1 - Number.EPSILON)

	if (weightedPosition < 0.5) {
		const season = seasonFor(date.getMonth() + 1, date.getDate())
		const seasonalLines = season ? seasonalEnglishLines[season] : []
		const availableEnglishLines = [...seasonalLines, ...englishLines]
		const text = quoteAtPosition(availableEnglishLines, weightedPosition * 2)

		return {
			text,
			englishTranslation: text,
			language: "English",
			languageCode: "en",
			direction: "ltr",
		}
	}

	return quoteAtPosition(translatedAuthQuotes, (weightedPosition - 0.5) * 2)
}

export const randomAuthQuote = (): AuthQuote => authQuoteForRandomValue(Math.random())
