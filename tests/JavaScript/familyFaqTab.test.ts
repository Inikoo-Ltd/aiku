import assert from "node:assert/strict"
import test from "node:test"
import {
	answeredFaqs,
	isTabVisible,
} from "../../resources/js/Iris/Components/BlocksUtils/FamilyExtraDescription2/tabVisibility.ts"

const emptyFaq = { question: null, answer: null, source_question: null, source_answer: null }
const answeredFaq = { question: "How heavy is each bath bomb?", answer: "<p>180 grams.</p>" }

test("hides the FAQ tab when every saved question is empty", () => {
	assert.equal(isTabVisible("faq", { faq: [emptyFaq, emptyFaq, { question: "  ", answer: "x" }] }, true), false)
	assert.equal(isTabVisible("faq", { faq: {} }, true), false)
	assert.equal(isTabVisible("faq", { faq: [] }, true), false)
})

test("shows the FAQ tab with only the questions that have text", () => {
	const family = { faq: [emptyFaq, answeredFaq, emptyFaq] }

	assert.equal(isTabVisible("faq", family, false), true)
	assert.deepEqual(answeredFaqs(family.faq), [answeredFaq])
})

test("treats a question made only of empty markup as empty", () => {
	assert.deepEqual(answeredFaqs([{ question: "<p>&nbsp;</p>", answer: "x" }]), [])
})
