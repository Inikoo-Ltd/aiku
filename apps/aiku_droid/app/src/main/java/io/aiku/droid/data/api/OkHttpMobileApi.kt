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
) : AuthApi, WarehouseApi, ClockingApi, HrApi {

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

    override suspend fun downloadAttachment(token: String, url: String, target: File): AttachmentDownload {
        val attachmentUrl = onApiHost(url) ?: return AttachmentDownload.Refused(message = "")
        val request = get(attachmentUrl, token)

        return withContext(ioDispatcher) {
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
                Log.w(LOG_TAG, "GET ${request.url} failed", exception)
                AttachmentDownload.Unreachable
            }
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
