import Foundation

extension GoodsOutStore {
    func configure(userID: Int?, warehouse: MobileWarehouse?, currentUser name: String) {
        guard let userID, userID > 0, let warehouse else {
            resetToSample()
            return
        }
        let abilities = warehouse.can
        rights = GoodsOutRights(
            isDispatchingSupervisor: abilities.superviseGoodsOut,
            canEditStock: false,
            canScanToPick: abilities.pickAndPack,
            canEditOrders: false
        )
        if !name.isEmpty {
            currentUser = name
        }
        guard !isRemote || self.userID != userID || warehouseID != warehouse.id else { return }
        enterRemote(userID: userID, warehouseID: warehouse.id)
    }

    func resetToSample() {
        guard isRemote else { return }
        leaveRemote()
        notes = GoodsOutSampleData.notes
        currentUser = GoodsOutSampleData.currentUser
        rights = GoodsOutRights()
        trolleys = GoodsOutSampleData.trolleys
        pickedBays = GoodsOutSampleData.pickedBays
        pickers = GoodsOutSampleData.pickers
        listQuery = GoodsOutListQuery()
        selectedForSession = []
    }

    func loadList() async {
        guard isRemote, let warehouseID else { return }
        let generation = beginListLoad()
        let request = GoodsOutMapper.listRequest(for: listQuery, page: 1, today: now())
        do {
            let page = try await service.deliveryNotes(warehouseID, request)
            guard isCurrentList(generation) else { return }
            applyListPage(page, replacing: true)
        } catch where error.isCancellation {
            finishListLoad(generation, error: nil)
        } catch {
            finishListLoad(generation, error: GoodsOutMapper.message(for: error))
        }
    }

    var hasMoreList: Bool { isRemote && listPage > 0 && listPage < listLastPage }

    func loadMoreList() async {
        guard isRemote, let warehouseID, hasMoreList, !isLoadingList else { return }
        let generation = beginListLoad()
        let request = GoodsOutMapper.listRequest(for: listQuery, page: listPage + 1, today: now())
        do {
            let page = try await service.deliveryNotes(warehouseID, request)
            guard isCurrentList(generation) else { return }
            applyListPage(page, replacing: false)
        } catch where error.isCancellation {
            finishListLoad(generation, error: nil)
        } catch {
            finishListLoad(generation, error: GoodsOutMapper.message(for: error))
        }
    }

    func loadCounts() async {
        guard isRemote, let warehouseID else { return }
        let picker = listQuery.filter.pickers.count == 1 ? listQuery.filter.pickers.first.map(GoodsOutMapper.pickerKey) : nil
        setCountsLoading(true)
        do {
            let loaded = try await service.counts(warehouseID, picker)
            setCounts(loaded, error: nil)
        } catch where error.isCancellation {
            setCountsLoading(false)
        } catch {
            setCounts(counts, error: GoodsOutMapper.message(for: error))
        }
    }

    func loadBacklogCounts() async {
        guard isRemote, let warehouseID else { return }
        setCountsLoading(true)
        do {
            setCounts(try await service.counts(warehouseID, nil), error: nil)
        } catch where error.isCancellation {
            setCountsLoading(false)
        } catch {
            setCounts(counts, error: GoodsOutMapper.message(for: error))
        }
    }

    @discardableResult
    func loadNote(reference: String) async -> GoodsOutDeliveryNote? {
        guard isRemote else { return note(reference) }
        guard let id = noteID(for: reference) else {
            setNoteError("This delivery note is not in the selected warehouse.", for: reference)
            return nil
        }
        return await loadNote(id: id, errorKey: reference)
    }

    @discardableResult
    func loadNote(id: Int) async -> GoodsOutDeliveryNote? {
        await loadNote(id: id, errorKey: Self.errorKey(id: id))
    }

    static func errorKey(id: Int) -> String { "id:\(id)" }

    func noteError(reference: String) -> String? { noteErrors[reference] }

    func noteError(id: Int) -> String? { noteErrors[Self.errorKey(id: id)] }

    func isLoadingNote(id: Int?) -> Bool {
        id.map { loadingNoteIDs.contains($0) } ?? false
    }

    func perform(_ action: GoodsOutNoteAction, on reference: String) async -> GoodsOutActionOutcome {
        await performForResult(action, on: reference).outcome
    }

    func performForResult(_ action: GoodsOutNoteAction, on reference: String) async -> (outcome: GoodsOutActionOutcome, result: GoodsOutActionResponseDTO.Result?) {
        guard isRemote else { return (.failed(message: "Sign in and choose a warehouse to work on delivery notes."), nil) }
        guard let id = noteID(for: reference) else { return (.failed(message: "This delivery note is not in the selected warehouse."), nil) }
        guard !isWorking(on: reference) else { return (.failed(message: "Still working on the last action."), nil) }
        setWorking(reference, true)
        defer { setWorking(reference, false) }
        do {
            let response = try await service.perform(id, action)
            store(response.data)
            let message = response.result?.message.flatMap(GoodsOutMapper.nonEmpty)
            return (response.isNothingChanged ? .nothingChanged(message: message) : .done(message: message), response.result)
        } catch {
            if error.isCancellation { return (.failed(message: ""), nil) }
            return (.failed(message: GoodsOutMapper.message(for: error)), nil)
        }
    }

    func scanToPick(_ reference: String, code: String, line: GoodsOutLine?) async -> (outcome: GoodsOutScanOutcome, message: String?) {
        let (outcome, result) = await performForResult(.scanPick(barcode: code, lineID: line?.apiID), on: reference)
        if case .failed(let message) = outcome {
            return (.notOnNote(code: code), message.isEmpty ? nil : message)
        }
        let item = result?.item
        let sko = item?.code ?? line?.sko ?? code
        let message = result?.message.flatMap(GoodsOutMapper.nonEmpty)
        switch result?.status {
        case "picked":
            return (.picked(sko: sko, left: GoodsOutMapper.quantity(item?.quantityToPick)), message)
        case "already_picked":
            return (.lineComplete(sko: sko), message)
        case "wrong_state":
            return (.notPicking, message)
        default:
            return (.notOnNote(code: code), message)
        }
    }

    func perform(_ actions: [GoodsOutNoteAction], on reference: String) async -> GoodsOutActionOutcome {
        var last = GoodsOutActionOutcome.nothingChanged(message: nil)
        for action in actions {
            last = await perform(action, on: reference)
            if case .failed = last { return last }
        }
        return last
    }

    func loadTrolleys() async {
        guard isRemote, let warehouseID else { return }
        await loadResource { [service] in
            GoodsOutMapper.trolleys(try await service.trolleys(warehouseID))
        } apply: { $0.trolleys = $1 }
    }

    func loadPickedBays() async {
        guard isRemote, let warehouseID else { return }
        await loadResource { [service] in
            GoodsOutMapper.pickedBays(try await service.pickedBays(warehouseID))
        } apply: { $0.pickedBays = $1 }
    }

    func loadPickers() async {
        guard isRemote, let warehouseID else { return }
        let userID = userID, name = currentUser
        await loadResource { [service] in
            GoodsOutMapper.pickers(try await service.pickers(warehouseID), currentUserID: userID, currentUser: name)
        } apply: { $0.pickers = $1 }
    }

    func loadPersonnel() async {
        guard isRemote, let warehouseID else { return }
        do {
            async let pickers = service.dispatchPersonnel(warehouseID, .pickers)
            async let packers = service.dispatchPersonnel(warehouseID, .packers)
            let (pickerPeople, packerPeople) = try await (pickers, packers)
            setPersonnel(
                pickers: GoodsOutMapper.activities(pickerPeople, role: .pickers),
                packers: GoodsOutMapper.activities(packerPeople, role: .packers),
                error: nil
            )
        } catch where error.isCancellation {
            return
        } catch {
            setPersonnel(pickers: pickersNow, packers: packersNow, error: GoodsOutMapper.message(for: error))
        }
    }

    func trolleyID(named code: String?) -> Int? {
        guard let code else { return nil }
        return trolleys.first { $0.code == code }?.apiID
    }

    func pickedBayID(named code: String?) -> Int? {
        guard let code else { return nil }
        return pickedBays.first { $0.name == code }?.apiID
    }
}
