package io.aiku.droid.data.offline

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.test.runTest
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant

class OfflineDownloadInterceptorTest {

    private val harness = OfflineHarness().start()
    private val prefetcher = DownloadPrefetcher(harness.api, harness.downloads, CoroutineScope(Dispatchers.Unconfined), Dispatchers.Unconfined)
    private var usePrefetcher = false
    private var reply: (Request) -> Pair<Int, String> = { 200 to "{}" }
    private val client = OkHttpClient.Builder()
        .addInterceptor(
            OfflineDownloadInterceptor(
                downloads = { harness.downloads },
                prefetcher = { prefetcher.takeIf { usePrefetcher } },
            ),
        )
        .addInterceptor { chain ->
            val (code, body) = reply(chain.request())
            Response.Builder()
                .request(chain.request())
                .protocol(Protocol.HTTP_1_1)
                .code(code)
                .message("")
                .header("Date", "Fri, 20 Nov 2026 09:30:00 GMT")
                .body(body.toResponseBody(JSON))
                .build()
        }
        .build()

    private fun call(method: String, path: String, answer: Pair<Int, String>, token: String = ANA.token) {
        reply = { answer }
        val request = Request.Builder()
            .url("https://app.aiku.test/app/api/mobile$path")
            .method(method, if (method == "GET") null else "{}".toRequestBody(JSON))
            .header("Authorization", "Bearer $token")
            .build()
        client.newCall(request).execute().close()
    }

    @Test
    fun `the detail answers are downloaded with the server time of the answer`() = runTest {
        call("GET", "/delivery-notes/5", 200 to NOTE_BODY)
        call("GET", "/warehouses/1/returns/31", 200 to """{"data":{"id":31}}""")
        call("GET", "/warehouses/1/stock-deliveries/12", 200 to """{"data":{"id":12}}""")
        call("GET", "/locations/77", 200 to """{"data":{"id":77}}""")
        call("GET", "/warehouses/1/org-stocks/88", 200 to """{"data":{"id":88}}""")

        val note = harness.work.downloaded(NOTE_5)
        assertEquals(NOTE_BODY, note?.body)
        assertEquals(Instant.parse("2026-11-20T09:30:00Z"), note?.seenAt)
        assertEquals(
            setOf(
                OfflineRecordType.DELIVERY_NOTE to 5L,
                OfflineRecordType.RETURN to 31L,
                OfflineRecordType.STOCK_DELIVERY to 12L,
                OfflineRecordType.LOCATION to 77L,
                OfflineRecordType.ORG_STOCK to 88L,
            ),
            harness.store.downloads.keys.map { it.type to it.id }.toSet(),
        )
    }

    @Test
    fun `lists and sub-pages are not downloaded, and another token is ignored`() {
        call("GET", "/warehouses/1/delivery-notes", 200 to """{"data":[]}""")
        call("GET", "/locations/77/stock-movements", 200 to """{"data":[]}""")
        call("GET", "/delivery-notes/5", 200 to NOTE_BODY, token = BEN.token)

        assertTrue(harness.store.downloads.isEmpty())
    }

    @Test
    fun `a 403 or 404 removes the download and a 401 purges every download but keeps the queue`() = runTest {
        call("GET", "/delivery-notes/5", 200 to NOTE_BODY)
        call("GET", "/delivery-notes/6", 200 to """{"data":{"id":6}}""")
        harness.online.value = false
        harness.pick(1)

        call("GET", "/delivery-notes/5", 404 to "{}")
        assertNull(harness.work.downloaded(NOTE_5))
        assertEquals(1, harness.store.downloads.size)

        call("GET", "/profile", 401 to "{}")

        assertTrue(harness.store.downloads.isEmpty())
        assertEquals(1, harness.store.actions.size)
        assertEquals(1, harness.work.waitingCount.value)
    }

    @Test
    fun `a write that answers with the record replaces its download`() = runTest {
        call("GET", "/delivery-notes/5", 200 to NOTE_BODY)
        val after = """{"data":{"id":5,"state":"handling","picked":3},"result":{"status":"ok"}}"""

        call("POST", "/delivery-notes/5/lines/11/pick", 200 to after)

        assertEquals(after, harness.work.downloaded(NOTE_5)?.body)
    }

    @Test
    fun `my work prefetches up to thirty assigned notes being picked or packed`() {
        usePrefetcher = true
        val rows = (1..40).joinToString(",") { id -> """{"kind":"delivery_note","id":$id,"reference":"AR$id","state":"handling"}""" } +
            """,{"kind":"delivery_note","id":90,"state":"packing"},{"kind":"delivery_note","id":91,"state":"packed"},{"kind":"return","id":92,"state":"returning"}"""

        call("GET", "/warehouses/1/my-work", 200 to """{"data":[$rows]}""")

        assertEquals((1..30).map { "delivery-notes/$it" }, harness.api.refetched)
    }

    @Test
    fun `my work prefetch includes notes in packing and waiting`() {
        usePrefetcher = true

        call(
            "GET",
            "/warehouses/1/my-work",
            200 to """{"data":[{"kind":"delivery_note","id":90,"state":"packing"},{"kind":"delivery_note","id":93,"state":"handling_blocked"},{"kind":"delivery_note","id":91,"state":"packed"}]}""",
        )

        assertEquals(listOf("delivery-notes/90", "delivery-notes/93"), harness.api.refetched)
    }

    @Test
    fun `the returns list prefetches the returns this person is processing`() {
        usePrefetcher = true
        val mine = """{"id":31,"state":"returning","handler":{"id":42,"name":"Ana"}}"""
        val someoneElse = """{"id":32,"state":"returning","handler":{"id":43,"name":"Ben"}}"""
        val notStarted = """{"id":33,"state":"received","handler":{"id":42,"name":"Ana"}}"""

        call("GET", "/warehouses/1/returns?state=all&page=1", 200 to """{"data":[$mine,$someoneElse,$notStarted],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":3}}""")

        assertEquals(listOf("warehouses/1/returns/31"), harness.api.refetched)
    }

    @Test
    fun `a record downloaded in the last minute is not fetched again`() {
        call("GET", "/delivery-notes/5", 200 to NOTE_BODY)
        usePrefetcher = true

        call("GET", "/warehouses/1/my-work", 200 to """{"data":[{"kind":"delivery_note","id":5,"state":"handling"},{"kind":"delivery_note","id":6,"state":"handling"}]}""")

        assertEquals(listOf("delivery-notes/6"), harness.api.refetched)
    }

    private companion object {
        val JSON = "application/json".toMediaType()
        const val NOTE_BODY = """{"data":{"id":5,"state":"handling"}}"""
    }
}
