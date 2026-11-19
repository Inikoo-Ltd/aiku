package io.aiku.droid.push

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxApi
import io.aiku.droid.data.session.SessionRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.channels.BufferOverflow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.getAndUpdate
import kotlinx.coroutines.launch

class PushInbox(
    private val inbox: InboxApi,
    private val sessions: SessionRepository,
    private val scope: CoroutineScope,
) {
    private val mutableArrivals = MutableSharedFlow<Unit>(extraBufferCapacity = 1, onBufferOverflow = BufferOverflow.DROP_OLDEST)
    val arrivals: SharedFlow<Unit> = mutableArrivals.asSharedFlow()

    private val mutablePending = MutableStateFlow<PushOpen?>(null)
    val pending: StateFlow<PushOpen?> = mutablePending.asStateFlow()

    fun messageArrived() {
        mutableArrivals.tryEmit(Unit)
    }

    fun receive(open: PushOpen) {
        if (sessions.session.value != null) {
            mutablePending.value = open
        }
    }

    fun take(): PushOpen? = mutablePending.getAndUpdate { null }

    fun markRead(notificationId: String, onRead: () -> Unit) {
        val token = sessions.session.value?.token ?: return
        scope.launch {
            if (inbox.markRead(token, notificationId) is GoodsOutResult.Loaded) {
                onRead()
            }
        }
    }
}
