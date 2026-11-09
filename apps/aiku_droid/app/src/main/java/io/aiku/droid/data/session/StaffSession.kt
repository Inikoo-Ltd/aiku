package io.aiku.droid.data.session

import kotlinx.serialization.Serializable

@Serializable
data class StaffSession(
    val token: String,
    val userId: Long,
    val username: String,
    val displayName: String,
)
