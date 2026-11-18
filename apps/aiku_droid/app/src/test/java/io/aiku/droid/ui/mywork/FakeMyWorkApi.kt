package io.aiku.droid.ui.mywork

import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.MyWorkApi
import io.aiku.droid.data.api.MyWorkPage
import io.aiku.droid.data.api.WorkBadges

class FakeMyWorkApi : MyWorkApi {
    val badgeRequests = mutableListOf<Pair<String, Long>>()
    var badgeResults: (Long) -> GoodsOutResult<WorkBadges> = { GoodsOutResult.Loaded(WorkBadges()) }
    val workRequests = mutableListOf<Long>()
    var work: GoodsOutResult<MyWorkPage> = GoodsOutResult.Loaded(MyWorkPage(emptyList()))

    override suspend fun badges(token: String, warehouseId: Long): GoodsOutResult<WorkBadges> {
        badgeRequests += token to warehouseId
        return badgeResults(warehouseId)
    }

    override suspend fun myWork(token: String, warehouseId: Long): GoodsOutResult<MyWorkPage> {
        workRequests += warehouseId
        return work
    }
}
