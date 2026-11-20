import SwiftUI

extension MobileStockDeliveryChecklist.Check: Identifiable {
    var id: String { key }
}

extension MobileStockDeliveryChecklist.Section: Identifiable {
    var id: String { key.isEmpty ? title : key }
}

struct GoodsInChecklistView: View {
    let delivery: MobileStockDelivery
    let checklist: MobileStockDeliveryChecklist
    let onOpenCheck: ((String) -> Void)?

    var body: some View {
        GoodsOutSectionTitle(title: "Goods-in checks", detail: "\(checklist.checked) of \(checklist.total) done")
        if checklist.sections.allSatisfy({ $0.checks.isEmpty }) {
            GoodsInHelper("This delivery has no goods-in checks.")
        }
        ForEach(checklist.sections.filter { !$0.checks.isEmpty }) { section in
            if !section.title.isEmpty {
                GoodsOutSectionTitle(title: section.title, detail: section.subtitle.flatMap { $0.isEmpty ? nil : $0 })
            }
            GoodsInCardList(items: section.checks) { check in
                GoodsInItemRow(
                    code: check.label,
                    subtitle: subtitle(check),
                    chip: chip(check),
                    showsPhoto: false,
                    identifier: "goodsIn.checklist.\(check.key)",
                    action: onOpenCheck.map { open in { open(check.key) } }
                )
            }
        }
        let differences = delivery.differences
        if !differences.isEmpty {
            GoodsOutSectionTitle(title: "Under / over delivered", detail: "read-only")
            GoodsInCardList(items: differences) { item in
                GoodsInItemRow(
                    code: item.code,
                    subtitle: item.name,
                    chip: GoodsInDifference.of(checked: item.skoChecked ?? 0, expected: item.skoExpected).chip,
                    imageURL: item.sko?.imageUrl
                )
            }
        }
        GoodsInHelper("A failed check needs a note. Purchasing follows up the differences.")
    }

    private func chip(_ check: MobileStockDeliveryChecklist.Check) -> Chip {
        switch GoodsInChecklistResult(rawValue: check.result ?? "") {
        case .passed: Chip("Passed", .success)
        case .failed: Chip("Failed", .danger)
        case nil: Chip("To do", .neutral)
        }
    }

    private func subtitle(_ check: MobileStockDeliveryChecklist.Check) -> String {
        let done = [check.checkedBy?.name, GoodsInText.moment(check.checkedAt)]
            .compactMap { $0 }
            .filter { !$0.isEmpty }
            .joined(separator: GoodsInText.separator)
        let status: String? = done.isEmpty ? (check.result == nil ? "Pass or fail" : nil) : done
        return [check.detail.flatMap { $0.isEmpty ? nil : $0 }, status].compactMap { $0 }.joined(separator: GoodsInText.separator)
    }
}

struct GoodsInCheckResultSheet: View {
    let check: MobileStockDeliveryChecklist.Check
    let checklist: MobileStockDeliveryChecklist
    let isWorking: Bool
    let problem: String?
    let errors: [String: String]
    let onSave: (GoodsInChecklistResult, String?, String?, Double?) -> Void
    let onDismiss: () -> Void
    @State private var result: GoodsInChecklistResult
    @State private var note = ""
    @State private var incidentType: String?
    @State private var quantity = ""
    @State private var isNoteMissing = false

    init(
        check: MobileStockDeliveryChecklist.Check,
        checklist: MobileStockDeliveryChecklist,
        isWorking: Bool,
        problem: String?,
        errors: [String: String],
        onSave: @escaping (GoodsInChecklistResult, String?, String?, Double?) -> Void,
        onDismiss: @escaping () -> Void
    ) {
        self.check = check
        self.checklist = checklist
        self.isWorking = isWorking
        self.problem = problem
        self.errors = errors
        self.onSave = onSave
        self.onDismiss = onDismiss
        _result = State(initialValue: GoodsInChecklistResult(rawValue: check.result ?? "") ?? .passed)
    }

    private var isFailing: Bool { result == .failed }

    private var noteError: String? {
        errors["body"] ?? (isNoteMissing ? "A failed check needs a note." : nil)
    }

    var body: some View {
        SheetScaffold(title: check.label, subtitle: check.detail.flatMap { $0.isEmpty ? nil : $0 }) {
            GoodsInChoiceRow(title: "Pass", isSelected: result == .passed, identifier: "goodsIn.checkResult.pass") { result = .passed }
            GoodsInChoiceRow(title: "Fail", isSelected: result == .failed, identifier: "goodsIn.checkResult.fail") { result = .failed }
            if isFailing {
                AikuTextField("Note", text: $note, error: noteError)
                    .onChange(of: note) { isNoteMissing = false }
                    .accessibilityIdentifier("goodsIn.checkResult.note")
                if !checklist.incidentTypes.isEmpty {
                    SheetSectionLabel("Incident type (optional)")
                    ForEach(checklist.incidentTypes, id: \.value) { type in
                        GoodsInChoiceRow(
                            title: type.label.isEmpty ? type.value : type.label,
                            isSelected: incidentType == type.value,
                            identifier: "goodsIn.checkResult.incident.\(type.value)"
                        ) {
                            incidentType = incidentType == type.value ? nil : type.value
                        }
                    }
                }
                AikuTextField("Incident quantity (optional)", text: $quantity, error: errors["incident_quantity"], keyboard: .decimalPad)
                    .accessibilityIdentifier("goodsIn.checkResult.quantity")
            }
            if let name = check.checkedBy?.name, !name.isEmpty {
                Text("Last recorded by \(name)")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            GoodsInHelper("A failed check needs a note. Purchasing follows up the differences.")
        } footer: {
            GoodsInSheetProblem(problem: problem)
            AikuButton(isFailing ? "Fail" : "Pass", kind: isFailing ? .dangerStrong : .finish, isLoading: isWorking) {
                if isFailing, note.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                    isNoteMissing = true
                } else {
                    onSave(result, note, incidentType, Double(quantity.trimmingCharacters(in: .whitespaces).replacingOccurrences(of: ",", with: ".")))
                }
            }
            .accessibilityIdentifier("goodsIn.checkResult.save")
            AikuButton("Cancel", kind: .tertiary, action: onDismiss)
        }
    }
}
