package io.aiku.droid.data.session

import kotlinx.coroutines.flow.StateFlow

interface SessionRepository {
    val session: StateFlow<StaffSession?>

    fun start(session: StaffSession, rememberOnDevice: Boolean)

    fun end()
}
