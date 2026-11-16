package io.aiku.droid.data.api

import android.util.Log
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import okio.buffer
import okio.sink
import java.io.File
import java.io.IOException
import java.time.LocalDate
import java.time.YearMonth

class OkHttpMobileApi(
    private val client: OkHttpClient,
    baseUrl: String,
    private val ioDispatcher: CoroutineDispatcher = Dispatchers.IO,
) : AuthApi, WarehouseApi, ClockingApi, HrApi, GoodsOutApi, PickingSessionApi, StaffChatApi {

    private val apiBaseUrl = baseUrl.trimEnd('/')
    private val mobileApiUrl = "$apiBaseUrl/app/api/mobile"
    private val hrApiUrl = "$mobileApiUrl/clocking"
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    override suspend fun login(credentials: LoginCredentials): LoginResult {
        val body = AuthResponses.json.encodeToString(
            LoginRequest(
                username = credentials.username,
                password = credentials.password,
                deviceName = credentials.deviceName,
                oneTimePassword = credentials.oneTimePassword,
                twoFactorSetupToken = credentials.twoFactorSetupToken,
            ),
        )

        return execute(post("$mobileApiUrl/login", body, bearerToken = null))
            ?.let { (statusCode, responseBody) -> AuthResponses.parseLogin(statusCode, responseBody) }
            ?: LoginResult.Unreachable
    }

    override suspend fun logout(token: String) {
        execute(post("$mobileApiUrl/logout", body = "{}", bearerToken = token))
    }

    override suspend fun requestPasswordResetCode(login: String): PasswordResult =
        password("$mobileApiUrl/password/reset-code", AuthResponses.json.encodeToString(PasswordResetCodeRequest(login)), bearerToken = null)

    override suspend fun resetPassword(reset: PasswordReset): PasswordResult {
        val body = AuthResponses.json.encodeToString(
            PasswordResetRequest(
                login = reset.login,
                code = reset.code,
                password = reset.password,
                passwordConfirmation = reset.passwordConfirmation,
            ),
        )

        return password("$mobileApiUrl/password/reset", body, bearerToken = null)
    }

    override suspend fun changePassword(token: String, change: PasswordChange): PasswordResult {
        val body = AuthResponses.json.encodeToString(
            PasswordChangeRequest(
                currentPassword = change.currentPassword,
                password = change.password,
                passwordConfirmation = change.passwordConfirmation,
            ),
        )

        return password("$mobileApiUrl/password", body, bearerToken = token)
    }

    private suspend fun password(url: String, body: String, bearerToken: String?): PasswordResult =
        execute(post(url, body, bearerToken))
            ?.let { (statusCode, responseBody) -> AuthResponses.parsePassword(statusCode, responseBody) }
            ?: PasswordResult.Unreachable

    override suspend fun profile(token: String): ProfileResult =
        execute(get("$mobileApiUrl/profile", token))
            ?.let { (statusCode, responseBody) -> AuthResponses.parseProfile(statusCode, responseBody) }
            ?: ProfileResult.Unavailable

    override suspend fun warehouses(token: String): WarehousesResult =
        execute(Request.Builder().url("$mobileApiUrl/warehouses").get().header("Authorization", "Bearer $token").build())
            ?.let { (statusCode, responseBody) -> WarehouseResponses.parse(statusCode, responseBody) }
            ?: WarehousesResult.Unreachable

    override suspend fun clocking(token: String): ClockingResult<ClockingStatus> =
        execute(get("$mobileApiUrl/clocking", token))
            ?.let { (statusCode, responseBody) -> ClockingResponses.parseClocking(statusCode, responseBody) }
            ?: ClockingResult.Unreachable

    override suspend fun timesheets(token: String, from: LocalDate?, to: LocalDate?): ClockingResult<TimesheetPeriod> {
        val url = "$mobileApiUrl/clocking/timesheets".toHttpUrl().newBuilder()
            .apply { from?.let { addQueryParameter("from", it.toString()) } }
            .apply { to?.let { addQueryParameter("to", it.toString()) } }
            .build()
            .toString()

        return execute(get(url, token))
            ?.let { (statusCode, responseBody) -> ClockingResponses.parseTimesheets(statusCode, responseBody) }
            ?: ClockingResult.Unreachable
    }

    override suspend fun shifts(token: String): ClockingResult<List<ShiftSchedule>> =
        execute(get("$mobileApiUrl/clocking/shifts", token))
            ?.let { (statusCode, responseBody) -> ClockingResponses.parseShifts(statusCode, responseBody) }
            ?: ClockingResult.Unreachable

    override suspend fun clockWithQr(token: String, request: QrClockingRequest): QrClockingResult =
        execute(post("$mobileApiUrl/clocking/qr", ClockingResponses.qrClockingBody(request), bearerToken = token))
            ?.let { (statusCode, responseBody) -> ClockingResponses.parseQrClocking(statusCode, responseBody) }
            ?: QrClockingResult.Unreachable

    override suspend fun saveNote(token: String, clockingId: Long, notes: String?): ClockingNoteResult {
        val request = Request.Builder()
            .url("$mobileApiUrl/clocking/$clockingId/notes")
            .patch(ClockingResponses.noteBody(notes).toRequestBody(jsonMediaType))
            .header("Authorization", "Bearer $token")
            .build()

        return execute(request)
            ?.let { (statusCode, responseBody) -> ClockingResponses.parseNote(statusCode, responseBody) }
            ?: ClockingNoteResult.Unreachable
    }

    override suspend fun leaves(token: String, page: Int, perPage: Int): ClockingResult<LeavePage> =
        execute(get(pagedUrl("leaves", page, perPage), token))
            ?.let { (statusCode, responseBody) -> HrResponses.parseLeaves(statusCode, responseBody) }
            ?: ClockingResult.Unreachable

    override suspend fun requestLeave(token: String, input: LeaveRequestInput): HrWriteResult<Leave> =
        executeBuilt {
            val body = if (input.attachments.isEmpty()) {
                HrResponses.leaveJson(input).toRequestBody(jsonMediaType)
            } else {
                multipart(HrResponses.leaveFields(input), input.attachments)
            }
            Request.Builder().url("$hrApiUrl/leaves").post(body).header("Authorization", "Bearer $token").build()
        }
            ?.let { (statusCode, responseBody) -> HrResponses.parseLeave(statusCode, responseBody) }
            ?: HrWriteResult.Unreachable

    override suspend fun replaceLeaveAttachments(token: String, leaveId: Long, attachments: List<UploadFile>): HrWriteResult<Leave> =
        executeBuilt {
            Request.Builder()
                .url("$hrApiUrl/leaves/$leaveId")
                .post(multipart(fields = emptyList(), attachments = attachments))
                .header("Authorization", "Bearer $token")
                .build()
        }
            ?.let { (statusCode, responseBody) -> HrResponses.parseLeave(statusCode, responseBody) }
            ?: HrWriteResult.Unreachable

    override suspend fun clockingAdjustments(token: String, page: Int, perPage: Int): ClockingResult<ClockingAdjustmentPage> =
        execute(get(pagedUrl("adjustments", page, perPage), token))
            ?.let { (statusCode, responseBody) -> HrResponses.parseClockingAdjustments(statusCode, responseBody) }
            ?: ClockingResult.Unreachable

    override suspend fun requestClockingAdjustment(token: String, input: ClockingAdjustmentInput): HrWriteResult<ClockingAdjustment> =
        executeBuilt {
            val body = if (input.attachments.isEmpty()) {
                HrResponses.clockingAdjustmentJson(input).toRequestBody(jsonMediaType)
            } else {
                multipart(HrResponses.clockingAdjustmentFields(input), input.attachments)
            }
            Request.Builder().url("$hrApiUrl/adjustments").post(body).header("Authorization", "Bearer $token").build()
        }
            ?.let { (statusCode, responseBody) -> HrResponses.parseClockingAdjustment(statusCode, responseBody) }
            ?: HrWriteResult.Unreachable

    override suspend fun cancelClockingAdjustment(token: String, adjustmentId: Long): HrWriteResult<CancelledRequest> =
        execute(Request.Builder().url("$hrApiUrl/adjustments/$adjustmentId").delete().header("Authorization", "Bearer $token").build())
            ?.let { (statusCode, responseBody) -> HrResponses.parseCancellation(statusCode, responseBody) }
            ?: HrWriteResult.Unreachable

    override suspend fun overtimeRequests(token: String, page: Int, perPage: Int): ClockingResult<OvertimePage> =
        execute(get(pagedUrl("overtime-requests", page, perPage), token))
            ?.let { (statusCode, responseBody) -> HrResponses.parseOvertimeRequests(statusCode, responseBody) }
            ?: ClockingResult.Unreachable

    override suspend fun requestOvertime(token: String, input: OvertimeRequestInput): HrWriteResult<OvertimeRequest> =
        execute(post("$hrApiUrl/overtime-requests", HrResponses.overtimeJson(input), bearerToken = token))
            ?.let { (statusCode, responseBody) -> HrResponses.parseOvertimeRequest(statusCode, responseBody) }
            ?: HrWriteResult.Unreachable

    override suspend fun updateOvertime(token: String, overtimeId: Long, input: OvertimeRequestInput): HrWriteResult<OvertimeRequest> {
        val request = Request.Builder()
            .url("$hrApiUrl/overtime-requests/$overtimeId")
            .patch(HrResponses.overtimeJson(input).toRequestBody(jsonMediaType))
            .header("Authorization", "Bearer $token")
            .build()

        return execute(request)
            ?.let { (statusCode, responseBody) -> HrResponses.parseOvertimeRequest(statusCode, responseBody) }
            ?: HrWriteResult.Unreachable
    }

    override suspend fun holidayCalendar(token: String, month: YearMonth): ClockingResult<HolidayCalendar> {
        val url = "$hrApiUrl/calendar".toHttpUrl().newBuilder().addQueryParameter("month", month.toString()).build().toString()

        return execute(get(url, token))
            ?.let { (statusCode, responseBody) -> HrResponses.parseHolidayCalendar(statusCode, responseBody) }
            ?: ClockingResult.Unreachable
    }

    override suspend fun deliveryNotes(token: String, warehouseId: Long, query: DeliveryNoteQuery): GoodsOutResult<DeliveryNotePage> =
        execute(get(urlWith("$mobileApiUrl/warehouses/$warehouseId/delivery-notes", GoodsOutResponses.deliveryNotesParameters(query)), token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseDeliveryNotes(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun deliveryNoteCounts(token: String, warehouseId: Long, picker: PickerFilter?): GoodsOutResult<DeliveryNoteCounts> =
        execute(get(urlWith("$mobileApiUrl/warehouses/$warehouseId/delivery-notes/counts", GoodsOutResponses.countsParameters(picker)), token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseCounts(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun deliveryNote(token: String, deliveryNoteId: Long): GoodsOutResult<DeliveryNote> =
        execute(get("$mobileApiUrl/delivery-notes/$deliveryNoteId", token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseDeliveryNote(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun scan(token: String, warehouseId: Long, code: String): GoodsOutResult<ScanLookup> =
        execute(get(urlWith("$mobileApiUrl/warehouses/$warehouseId/scan", listOf("code" to code)), token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseScan(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun write(token: String, deliveryNoteId: Long, write: DeliveryNoteWrite): GoodsOutResult<NoteActionOutcome> =
        execute(writeRequest("$mobileApiUrl/delivery-notes/$deliveryNoteId", GoodsOutResponses.writeRequest(write), token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseWrite(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    private fun writeRequest(baseUrl: String, request: WriteRequest, token: String): Request {
        val builder = Request.Builder()
            .url("$baseUrl/${request.path}")
            .header("Authorization", "Bearer $token")
        return when (request.method) {
            WriteMethod.Post -> builder.post((request.body?.toString() ?: "{}").toRequestBody(jsonMediaType))
            WriteMethod.Delete -> builder.delete()
        }.build()
    }

    override suspend fun pickingSessions(token: String, warehouseId: Long, state: PickingSessionState?, page: Int): GoodsOutResult<PickingSessionPage> =
        execute(get(urlWith("$mobileApiUrl/warehouses/$warehouseId/picking-sessions", PickingSessionResponses.listParameters(state, page)), token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parseList(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun pickingSession(token: String, sessionId: Long): GoodsOutResult<PickingSession> =
        execute(get("$mobileApiUrl/picking-sessions/$sessionId", token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parseSession(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun createPickingSession(token: String, warehouseId: Long, deliveryNoteIds: List<Long>): GoodsOutResult<PickingSession> =
        execute(post("$mobileApiUrl/warehouses/$warehouseId/picking-sessions", PickingSessionResponses.createBody(deliveryNoteIds).toString(), bearerToken = token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parseSession(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun writeSession(token: String, sessionId: Long, write: PickingSessionWrite): GoodsOutResult<SessionActionOutcome> =
        execute(writeRequest("$mobileApiUrl/picking-sessions/$sessionId", PickingSessionResponses.writeRequest(write), token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parseWrite(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun sessionScanPick(token: String, sessionId: Long, request: ScanPickRequest): GoodsOutResult<SessionScanPickOutcome> =
        execute(post("$mobileApiUrl/picking-sessions/$sessionId/scan-pick", PickingSessionResponses.scanPickBody(request).toString(), bearerToken = token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parseScanPick(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun sessionScanPack(token: String, sessionId: Long, request: ScanPackRequest): GoodsOutResult<SessionScanPackOutcome> =
        execute(post("$mobileApiUrl/picking-sessions/$sessionId/scan-pack", PickingSessionResponses.scanPackBody(request).toString(), bearerToken = token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parseScanPack(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun dispatchPersonnel(token: String, warehouseId: Long, role: DispatchRole): GoodsOutResult<List<DispatchPerson>> =
        execute(get(urlWith("$mobileApiUrl/warehouses/$warehouseId/dispatch-personnel", PickingSessionResponses.personnelParameters(role)), token))
            ?.let { (statusCode, responseBody) -> PickingSessionResponses.parsePersonnel(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun openChat(token: String, kind: ChatContextKind, contextId: Long): GoodsOutResult<ChatConversation> =
        execute(post("$mobileApiUrl/${StaffChatResponses.openPath(kind, contextId)}", body = "{}", bearerToken = token))
            ?.let { (statusCode, responseBody) -> StaffChatResponses.parseConversation(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun chatMessages(token: String, ulid: String, query: ChatPageQuery): GoodsOutResult<ChatMessagePage> =
        execute(get(urlWith("$mobileApiUrl/chat/$ulid/messages", StaffChatResponses.messagesParameters(query)), token))
            ?.let { (statusCode, responseBody) -> StaffChatResponses.parseMessages(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun sendChatMessage(token: String, ulid: String, body: String): GoodsOutResult<ChatMessage> =
        execute(post("$mobileApiUrl/chat/$ulid/messages", StaffChatResponses.sendBody(body).toString(), bearerToken = token))
            ?.let { (statusCode, responseBody) -> StaffChatResponses.parseSent(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun scanPick(token: String, deliveryNoteId: Long, request: ScanPickRequest): GoodsOutResult<ScanPickOutcome> =
        execute(post("$mobileApiUrl/delivery-notes/$deliveryNoteId/scan-pick", GoodsOutResponses.scanPickBody(request).toString(), bearerToken = token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseScanPick(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun trolleys(token: String, warehouseId: Long, availableOnly: Boolean): GoodsOutResult<List<Trolley>> =
        execute(get(urlWith("$mobileApiUrl/warehouses/$warehouseId/trolleys", GoodsOutResponses.trolleysParameters(availableOnly)), token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseTrolleys(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun pickedBays(token: String, warehouseId: Long): GoodsOutResult<List<PickedBay>> =
        execute(get("$mobileApiUrl/warehouses/$warehouseId/picked-bays", token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parsePickedBays(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun pickers(token: String, warehouseId: Long): GoodsOutResult<List<Picker>> =
        execute(get("$mobileApiUrl/warehouses/$warehouseId/pickers", token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parsePickers(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun scanPack(token: String, deliveryNoteId: Long, request: ScanPackRequest): GoodsOutResult<ScanPackOutcome> =
        execute(post("$mobileApiUrl/delivery-notes/$deliveryNoteId/scan-pack", GoodsOutResponses.scanPackBody(request).toString(), bearerToken = token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseScanPack(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun shippers(token: String, deliveryNoteId: Long): GoodsOutResult<ShipperList> =
        execute(get("$mobileApiUrl/delivery-notes/$deliveryNoteId/shippers", token))
            ?.let { (statusCode, responseBody) -> GoodsOutResponses.parseShippers(statusCode, responseBody) }
            ?: GoodsOutResult.Unreachable

    override suspend fun downloadDocument(token: String, url: String, target: File): AttachmentDownload {
        val documentUrl = onApiHost(url) ?: return AttachmentDownload.Refused(message = "")
        return downloadTo(get(documentUrl, token), target)
    }

    override suspend fun downloadInsertPdf(token: String, deliveryNoteId: Long, leafletId: Long, target: File): AttachmentDownload =
        downloadTo(post("$mobileApiUrl/delivery-notes/$deliveryNoteId/${GoodsOutResponses.insertPdfPath(leafletId)}", body = "{}", bearerToken = token), target)

    private fun urlWith(path: String, parameters: List<Pair<String, String>>): String = path.toHttpUrl().newBuilder()
        .apply { parameters.forEach { (name, value) -> addQueryParameter(name, value) } }
        .build()
        .toString()

    override suspend fun downloadAttachment(token: String, url: String, target: File): AttachmentDownload {
        val attachmentUrl = onApiHost(url) ?: return AttachmentDownload.Refused(message = "")
        return downloadTo(get(attachmentUrl, token), target)
    }

    private suspend fun downloadTo(request: Request, target: File): AttachmentDownload =
        withContext(ioDispatcher) {
            try {
                client.newCall(request).execute().use { response ->
                    if (response.code == HTTP_OK) {
                        target.parentFile?.mkdirs()
                        target.sink().buffer().use { sink -> sink.writeAll(response.body.source()) }
                        AttachmentDownload.Saved(file = target, mimeType = response.body.contentType()?.let { "${it.type}/${it.subtype}" })
                    } else {
                        HrResponses.parseDownloadFailure(response.code, response.body.string())
                    }
                }
            } catch (exception: IOException) {
                Log.w(LOG_TAG, "${request.method} ${request.url} failed", exception)
                AttachmentDownload.Unreachable
            }
        }

    private fun pagedUrl(path: String, page: Int, perPage: Int): String = "$hrApiUrl/$path".toHttpUrl().newBuilder()
        .addQueryParameter("page", page.toString())
        .addQueryParameter("per_page", perPage.toString())
        .build()
        .toString()

    private fun onApiHost(url: String): String? {
        val given = url.toHttpUrlOrNull() ?: return null
        return apiBaseUrl.toHttpUrl().newBuilder()
            .encodedPath(given.encodedPath)
            .encodedQuery(given.encodedQuery)
            .build()
            .toString()
    }

    private fun multipart(fields: List<Pair<String, String>>, attachments: List<UploadFile>): RequestBody = MultipartBody.Builder()
        .setType(MultipartBody.FORM)
        .apply { fields.forEach { (name, value) -> addFormDataPart(name, value) } }
        .apply {
            attachments.forEach { file ->
                addFormDataPart(ATTACHMENTS_FIELD, file.name, file.readBytes().toRequestBody(file.mimeType.toMediaTypeOrNull()))
            }
        }
        .build()

    private fun get(url: String, bearerToken: String): Request = Request.Builder()
        .url(url)
        .get()
        .header("Authorization", "Bearer $bearerToken")
        .build()

    private fun post(url: String, body: String, bearerToken: String?): Request = Request.Builder()
        .url(url)
        .post(body.toRequestBody(jsonMediaType))
        .apply { bearerToken?.let { header("Authorization", "Bearer $it") } }
        .build()

    private suspend fun execute(request: Request): Pair<Int, String>? = withContext(ioDispatcher) {
        try {
            client.newCall(request).execute().use { response -> response.code to response.body.string() }
        } catch (exception: IOException) {
            Log.w(LOG_TAG, "${request.method} ${request.url} failed", exception)
            null
        }
    }

    private suspend fun executeBuilt(buildRequest: () -> Request): Pair<Int, String>? = withContext(ioDispatcher) {
        try {
            val request = buildRequest()
            client.newCall(request).execute().use { response -> response.code to response.body.string() }
        } catch (exception: IOException) {
            Log.w(LOG_TAG, "Request with attachments failed", exception)
            null
        } catch (exception: SecurityException) {
            Log.w(LOG_TAG, "An attachment could not be read", exception)
            null
        }
    }

    private companion object {
        const val LOG_TAG = "AikuApi"
        const val HTTP_OK = 200
        const val ATTACHMENTS_FIELD = "attachments[]"
    }
}
