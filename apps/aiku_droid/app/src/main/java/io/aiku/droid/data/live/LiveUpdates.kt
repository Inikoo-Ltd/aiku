package io.aiku.droid.data.live

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.RealtimeApi
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.emptyFlow
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObjectBuilder
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.intOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.put
import kotlinx.serialization.json.putJsonObject
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener

data class LiveEvent(val channel: String, val event: String, val data: String)

interface LiveChannels {
    val isConnected: StateFlow<Boolean>

    fun events(channel: String): Flow<LiveEvent>
}

object NoLiveChannels : LiveChannels {
    override val isConnected: StateFlow<Boolean> = MutableStateFlow(false)

    override fun events(channel: String): Flow<LiveEvent> = emptyFlow()
}

object LiveChannelNames {
    fun personal(userId: Long): String = "private-grp.personal.$userId"

    fun record(type: String, id: Long): String = "private-grp.mobile.$type.$id"

    fun dispatchingBacklog(organisationId: Long): String = "private-grp.org.$organisationId.dispatching-backlog"
}

object LiveEventNames {
    const val RECORD_CHANGED = "record-changed"
    const val PERMISSIONS_CHANGED = "permissions-changed"
    const val PERSONAL_NOTIFICATION = "personal-notification"
    const val TASK_BADGES = "task-badges-update"
    const val TICKET_BADGES = "ticket-badges-update"
    const val STAFF_MESSAGE = "staff-message"
    const val DISPATCHING_BACKLOG = "dispatching-backlog-changed"
}

/**
 * The Pusher protocol (version 7) over OkHttp's WebSocket while the app is in the foreground: private
 * channels are signed by the mobile API, a channel is subscribed while someone collects its events, and
 * a dropped connection comes back with a growing pause. An event is only a hint to reload.
 */
class LiveUpdates(
    private val client: OkHttpClient,
    private val api: RealtimeApi,
    private val sessions: SessionRepository,
    private val scope: CoroutineScope,
    private val clientName: String,
    private val clientVersion: String,
) : LiveChannels {

    private val json = Json { ignoreUnknownKeys = true }
    private val lock = Any()
    private val wanted = mutableMapOf<String, Int>()
    private val incoming = MutableSharedFlow<LiveEvent>(extraBufferCapacity = EVENT_BUFFER)
    private val connected = MutableStateFlow(false)
    override val isConnected: StateFlow<Boolean> = connected.asStateFlow()

    private var socket: WebSocket? = null
    private var socketId: String? = null
    private var isForeground = false
    private var connectJob: Job? = null
    private var pingJob: Job? = null
    private var reconnectJob: Job? = null
    private var attempt = 0

    fun start() {
        scope.launch {
            sessions.session.collect { session ->
                if (session == null) {
                    disconnect()
                } else {
                    connect()
                }
            }
        }
    }

    fun onForeground() {
        isForeground = true
        connect()
    }

    fun onBackground() {
        isForeground = false
        disconnect()
    }

    override fun events(channel: String): Flow<LiveEvent> = flow {
        retain(channel)
        try {
            incoming.filter { it.channel == channel }.collect { emit(it) }
        } finally {
            release(channel)
        }
    }

    private fun retain(channel: String) {
        val isFirst = synchronized(lock) {
            val count = (wanted[channel] ?: 0) + 1
            wanted[channel] = count
            count == 1
        }
        if (isFirst) {
            subscribe(channel)
        }
    }

    private fun release(channel: String) {
        val isLast = synchronized(lock) {
            val count = (wanted[channel] ?: 0) - 1
            if (count <= 0) {
                wanted.remove(channel)
            } else {
                wanted[channel] = count
            }
            count <= 0
        }
        if (isLast) {
            socket?.send(message(UNSUBSCRIBE) { put(CHANNEL, channel) })
        }
    }

    private fun connect() {
        val token = sessions.session.value?.token ?: return
        if (!isForeground || socket != null || connectJob?.isActive == true) {
            return
        }
        reconnectJob?.cancel()
        connectJob = scope.launch {
            val server = when (val result = api.realtimeServer(token)) {
                is GoodsOutResult.Loaded -> result.value ?: return@launch
                GoodsOutResult.SessionExpired -> return@launch
                else -> {
                    scheduleReconnect()
                    return@launch
                }
            }
            val request = Request.Builder().url(server.socketUrl(clientName, clientVersion)).build()
            socket = client.newWebSocket(request, Listener())
        }
    }

    private fun disconnect() {
        connectJob?.cancel()
        reconnectJob?.cancel()
        pingJob?.cancel()
        socket?.close(NORMAL_CLOSURE, null)
        clearSocket()
    }

    private fun clearSocket() {
        socket = null
        socketId = null
        connected.value = false
    }

    private fun scheduleReconnect() {
        if (!isForeground || sessions.session.value == null) {
            return
        }
        val pause = (FIRST_PAUSE_MILLIS shl attempt.coerceAtMost(MAX_DOUBLINGS)).coerceAtMost(MAX_PAUSE_MILLIS)
        attempt++
        reconnectJob?.cancel()
        reconnectJob = scope.launch {
            delay(pause)
            connect()
        }
    }

    private fun subscribe(channel: String) {
        val id = socketId ?: return
        val token = sessions.session.value?.token ?: return
        scope.launch {
            val auth = api.authorizeChannel(token, id, channel) ?: return@launch
            if (socketId == id) {
                socket?.send(
                    message(SUBSCRIBE) {
                        put(AUTH, auth)
                        put(CHANNEL, channel)
                    },
                )
            }
        }
    }

    private fun established(data: String) {
        val payload = runCatching { json.parseToJsonElement(data).jsonObject }.getOrNull() ?: return
        socketId = payload[SOCKET_ID]?.jsonPrimitive?.contentOrNull ?: return
        val activitySeconds = payload[ACTIVITY_TIMEOUT]?.jsonPrimitive?.intOrNull ?: DEFAULT_ACTIVITY_SECONDS
        attempt = 0
        connected.value = true
        synchronized(lock) { wanted.keys.toList() }.forEach(::subscribe)
        pingJob?.cancel()
        pingJob = scope.launch {
            while (isActive) {
                delay(activitySeconds * MILLIS_PER_SECOND)
                socket?.send(message(PING) {})
            }
        }
    }

    internal fun receive(text: String) {
        val frame = runCatching { json.parseToJsonElement(text).jsonObject }.getOrNull() ?: return
        val event = frame[EVENT]?.jsonPrimitive?.contentOrNull ?: return
        val data = frame[DATA]?.let { element -> (element as? JsonPrimitive)?.contentOrNull ?: element.toString() }.orEmpty()
        when (event) {
            CONNECTION_ESTABLISHED -> established(data)
            PING -> socket?.send(message(PONG) {})
            else -> frame[CHANNEL]?.jsonPrimitive?.contentOrNull
                ?.takeUnless { event.startsWith(INTERNAL_PREFIX) || event.startsWith(PUSHER_PREFIX) }
                ?.let { channel -> incoming.tryEmit(LiveEvent(channel = channel, event = event, data = data)) }
        }
    }

    private fun message(event: String, data: JsonObjectBuilder.() -> Unit): String = buildJsonObject {
        put(EVENT, event)
        putJsonObject(DATA, data)
    }.toString()

    private inner class Listener : WebSocketListener() {
        override fun onMessage(webSocket: WebSocket, text: String) {
            scope.launch { if (webSocket == socket) receive(text) }
        }

        override fun onClosed(webSocket: WebSocket, code: Int, reason: String) = dropped(webSocket)

        override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) = dropped(webSocket)

        private fun dropped(webSocket: WebSocket) {
            scope.launch {
                if (webSocket == socket) {
                    pingJob?.cancel()
                    clearSocket()
                    scheduleReconnect()
                }
            }
        }
    }

    private companion object {
        const val EVENT = "event"
        const val DATA = "data"
        const val CHANNEL = "channel"
        const val AUTH = "auth"
        const val SOCKET_ID = "socket_id"
        const val ACTIVITY_TIMEOUT = "activity_timeout"
        const val CONNECTION_ESTABLISHED = "pusher:connection_established"
        const val SUBSCRIBE = "pusher:subscribe"
        const val UNSUBSCRIBE = "pusher:unsubscribe"
        const val PING = "pusher:ping"
        const val PONG = "pusher:pong"
        const val PUSHER_PREFIX = "pusher:"
        const val INTERNAL_PREFIX = "pusher_internal:"
        const val NORMAL_CLOSURE = 1000
        const val EVENT_BUFFER = 64
        const val DEFAULT_ACTIVITY_SECONDS = 30
        const val MILLIS_PER_SECOND = 1000L
        const val FIRST_PAUSE_MILLIS = 1000L
        const val MAX_PAUSE_MILLIS = 60_000L
        const val MAX_DOUBLINGS = 6
    }
}
