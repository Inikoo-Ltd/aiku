import Foundation
import Testing
@testable import Aiku

private actor SessionListCalls {
    var queries: [MobilePickingSessionsQuery] = []

    func record(_ query: MobilePickingSessionsQuery) { queries.append(query) }
}

private actor SessionListAnswer {
    private var result: Result<MobilePickingSessionsPage, APIError>

    init(_ result: Result<MobilePickingSessionsPage, APIError>) {
        self.result = result
    }

    func set(_ result: Result<MobilePickingSessionsPage, APIError>) { self.result = result }

    func next() throws -> MobilePickingSessionsPage { try result.get() }
}

private func listService(calls: SessionListCalls, answer: SessionListAnswer) -> PickingSessionService {
    PickingSessionService(
        sessions: { _, query in
            await calls.record(query)
            return try await answer.next()
        },
        session: { _ in throw APIError.offline },
        create: { _, _ in throw APIError.offline },
        write: { _, _ in throw APIError.offline },
        scanPick: { _, _ in throw APIError.offline },
        scanPack: { _, _ in throw APIError.offline },
        trolleys: { _, _ in [] },
        pickedBays: { _ in [] },
        candidates: { _ in [] }
    )
}

private func row(
    _ id: Int,
    _ reference: String,
    state: String = "handling",
    picker: String? = nil,
    startAt: String? = nil,
    endAt: String? = nil,
    createdAt: String? = nil
) -> MobilePickingSessionRow {
    MobilePickingSessionRow(
        id: id,
        reference: reference,
        state: state,
        picker: picker.map { MobilePickingSessionPerson(id: id, name: $0) },
        startAt: startAt,
        endAt: endAt,
        createdAt: createdAt
    )
}

private let savedSessions = [
    row(1, "PS-0001", state: "packing_finished", picker: "Zoe", startAt: "2026-11-10T08:00:00+00:00", endAt: "2026-11-10T12:00:00+00:00"),
    row(2, "ps-0002", state: "handling", picker: "adam", startAt: "2026-11-12T08:00:00+00:00"),
    row(3, "PS-0003", state: "in_process", createdAt: "2026-11-13T08:00:00+00:00"),
    row(4, "PS-0104", state: "handling", picker: "Mia", startAt: "2026-11-11T08:00:00+00:00", endAt: "2026-11-11T09:00:00+00:00"),
]

@MainActor
struct PickingSessionListFilterTests {
    private let utc = TimeZone(identifier: "UTC")!

    @Test("The list request sends the state, the trimmed search as q, the sort key and the page")
    func queryItems() {
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "http://localhost/app/api")!))

        let full = client.makeRequest(for: .pickingSessions(warehouseID: 5, query: MobilePickingSessionsQuery(state: "handling", search: "  PS-1 ", sort: .started, page: 2)))
        #expect(full.url?.absoluteString == "http://localhost/app/api/mobile/warehouses/5/picking-sessions?state=handling&q=PS-1&sort=-start_at&page=2")

        let plain = client.makeRequest(for: .pickingSessions(warehouseID: 5, query: MobilePickingSessionsQuery(search: "   ")))
        #expect(plain.url?.absoluteString == "http://localhost/app/api/mobile/warehouses/5/picking-sessions?page=1")

        #expect(MobilePickingSessionsQuery(sort: .reference).queryItems.contains(URLQueryItem(name: "sort", value: "reference")))
        #expect(MobilePickingSessionsQuery(sort: .picker).queryItems.contains(URLQueryItem(name: "sort", value: "user_name")))
        #expect(MobilePickingSessionsQuery(sort: .finished).queryItems.contains(URLQueryItem(name: "sort", value: "-end_at")))
    }

    @Test("The sort select starts with the server order and offers the sorts Aiku accepts")
    func sortOptions() {
        let options = PickingSessionSort.options

        #expect(options.first == ListSortOption<PickingSessionSort>(sort: nil, label: "Newest first"))
        #expect(options.dropFirst().map(\.sort) == [.reference, .picker, .started, .finished])
        #expect(options.dropFirst().map(\.label) == ["Reference", "Picker", "Started", "Finished"])
    }

    @Test("Rows decode the created, started and finished dates, saved or fresh")
    func decodesDates() throws {
        let body = Data("""
        {"data": [
          {"id": 8, "reference": "PS-8", "state": "in_process", "state_label": "In process", "picker": null,
           "number_delivery_notes": 2, "number_items": 6, "number_trolleys": 0, "picking_percentage": 0, "packing_percentage": 0,
           "start_at": null, "end_at": null, "created_at": "2026-11-18T09:30:00+00:00"},
          {"id": 9, "reference": "PS-9", "state": "handling"}
        ], "counts": {}, "pagination": {"current_page": 1, "last_page": 1, "per_page": 25, "total": 2}}
        """.utf8)
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let page = try decoder.decode(MobilePickingSessionsPage.self, from: body)

        #expect(page.data[0].createdAt == "2026-11-18T09:30:00+00:00")
        #expect(page.data[0].startAt == nil)
        #expect(page.data[1].createdAt == nil)

        let lists = SavedLists(root: OfflineTestData.temporaryDirectory())
        lists.remember(userID: 7, warehouseID: 5, kind: .pickingSessions, page: body)
        let saved = try #require(lists.rows(MobilePickingSessionRow.self, userID: 7, warehouseID: 5, kind: .pickingSessions))
        #expect(saved.rows.map(\.id) == [8, 9])
        #expect(saved.rows[0].createdAt == "2026-11-18T09:30:00+00:00")
        #expect(saved.rows[0].numberItems == 6)
    }

    @Test("A row shows the start date as Started, else the creation date as Created, with the year")
    func rowDate() {
        let started = MobilePickingSessionRow(id: 1, reference: "PS-1", state: "handling", stateLabel: "Picking", startAt: "2026-11-16T10:00:00+00:00", createdAt: "2026-11-15T10:00:00+00:00")
        let created = MobilePickingSessionRow(id: 2, reference: "PS-2", state: "in_process", stateLabel: "In process", createdAt: "2026-11-15T10:00:00+00:00")
        let undated = MobilePickingSessionRow(id: 3, reference: "PS-3", state: "in_process")
        let repeating = MobilePickingSessionRow(id: 4, reference: "PS-4", state: "handling", stateLabel: "Started", startAt: "2026-11-16T10:00:00+00:00")

        #expect(started.recordDate(in: utc) == "16 Nov 2026")
        #expect(started.recordDateCaption(in: utc) == RecordDateCaption.started)
        #expect(created.recordDate(in: utc) == "15 Nov 2026")
        #expect(created.recordDateCaption(in: utc) == RecordDateCaption.created)
        #expect(undated.recordDate(in: utc) == nil)
        #expect(undated.recordDateCaption(in: utc) == nil)
        #expect(repeating.recordDateCaption(in: utc) == nil)
        #expect(created.details == ["0 delivery notes", "0 items", "0 trolleys", "0% picked"])
    }

    @Test("Saved rows are kept to the state and the reference search, in the server's order by default")
    func offlineFilterAndSearch() {
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery()).map(\.id) == [4, 3, 2, 1])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(state: "handling")).map(\.id) == [4, 2])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(search: " ps-000")).map(\.id) == [3, 2, 1])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(search: "104")).map(\.id) == [4])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(state: "in_process", search: "0002")).isEmpty)
    }

    @Test("Saved rows sort by reference and picker A to Z, and by start or finish newest first, empty values last")
    func offlineSorts() {
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(sort: .reference)).map(\.id) == [1, 2, 3, 4])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(sort: .picker)).map(\.id) == [2, 4, 1, 3])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(sort: .started)).map(\.id) == [2, 4, 1, 3])
        #expect(PickingSessionOfflineList.rows(savedSessions, query: MobilePickingSessionsQuery(sort: .finished)).map(\.id) == [4, 1, 3, 2])
    }

    @Test("Offline, the first page shows the saved rows filtered and sorted on the phone, and a fresh load replaces them")
    func offlineStore() async {
        let calls = SessionListCalls()
        let answer = SessionListAnswer(.failure(.offline))
        let savedAt = Date(timeIntervalSince1970: 1_800_000_000)
        let store = PickingSessionsStore(service: listService(calls: calls, answer: answer)) { warehouseID in
            warehouseID == 5 ? SavedRows(rows: savedSessions, savedAt: savedAt) : nil
        }

        await store.load(warehouseID: 5)
        #expect(store.rows.map(\.id) == [4, 3, 2, 1])
        #expect(store.savedCopyAt == savedAt)
        #expect(store.loadError != nil)
        #expect(store.shownTotal == 4)
        #expect(!store.canLoadMore)

        await store.select(.state(.handling))
        #expect(store.rows.map(\.id) == [4, 2])

        await store.apply(ListFilter(sort: .started))
        #expect(store.rows.map(\.id) == [2, 4])
        #expect(store.listFilter.activeCount == 1)

        await answer.set(.success(MobilePickingSessionsPage(
            data: [row(20, "PS-0020")],
            pagination: MobileSessionPagination(currentPage: 1, lastPage: 3, perPage: 25, total: 61)
        )))
        await store.clearListFilter()
        #expect(store.rows.map(\.id) == [20])
        #expect(store.savedCopyAt == nil)
        #expect(store.loadError == nil)
        #expect(store.shownTotal == 61)
        #expect(store.canLoadMore)

        let queries = await calls.queries
        #expect(queries.map(\.state) == [nil, "handling", "handling", "handling"])
        #expect(queries.map(\.sort) == [nil, nil, .started, nil])
    }

    @Test("A failure other than no connection keeps the error and shows no saved rows")
    func serverErrorShowsNoSavedRows() async {
        let store = PickingSessionsStore(service: listService(calls: SessionListCalls(), answer: SessionListAnswer(.failure(.http(statusCode: 500))))) { _ in
            SavedRows(rows: savedSessions, savedAt: Date())
        }

        await store.load(warehouseID: 5)

        #expect(store.rows.isEmpty)
        #expect(store.savedCopyAt == nil)
        #expect(store.loadError != nil)
    }

    @Test("Typing a search waits, then reloads the first page with q")
    func searchReloads() async throws {
        let calls = SessionListCalls()
        let answer = SessionListAnswer(.success(MobilePickingSessionsPage(data: [row(1, "PS-0001")])))
        let store = PickingSessionsStore(service: listService(calls: calls, answer: answer)) { _ in nil }
        await store.load(warehouseID: 5)

        store.search("PS-00")
        store.search("PS-0001")
        #expect(store.searchText == "PS-0001")
        #expect(store.isSearching)

        var queries = await calls.queries
        var attempts = 0
        while attempts < 60, queries.count < 2 || store.loadingPage != nil || !store.hasLoaded {
            try await Task.sleep(for: .milliseconds(50))
            queries = await calls.queries
            attempts += 1
        }

        #expect(queries.map(\.search) == ["", "PS-0001"])
        #expect(queries.last?.page == 1)
        #expect(store.rows.map(\.id) == [1])
    }
}
