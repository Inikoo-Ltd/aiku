import SwiftUI

struct FieldLabel: View {
    let text: String
    var isDisabled = false

    init(_ text: String, isDisabled: Bool = false) {
        self.text = text
        self.isDisabled = isDisabled
    }

    var body: some View {
        Text(text)
            .font(.aiku(14, .medium))
            .foregroundStyle(isDisabled ? AikuColor.text4 : AikuColor.text2)
    }
}

struct FieldError: View {
    let message: String

    init(_ message: String) {
        self.message = message
    }

    var body: some View {
        HStack(spacing: 6) {
            AppIcon.error.image.font(.system(size: 14))
            Text(message).font(.aiku(13))
        }
        .foregroundStyle(AikuColor.danger)
        .accessibilityElement(children: .combine)
    }
}

struct FieldBox<Content: View>: View {
    var isFocused = false
    var hasError = false
    var isDisabled = false
    var height: CGFloat = 50
    var radius: CGFloat = 8
    var growsVertically = false
    @ViewBuilder var content: Content

    init(
        isFocused: Bool = false,
        hasError: Bool = false,
        isDisabled: Bool = false,
        height: CGFloat = 50,
        radius: CGFloat = 8,
        growsVertically: Bool = false,
        @ViewBuilder content: () -> Content
    ) {
        self.isFocused = isFocused
        self.hasError = hasError
        self.isDisabled = isDisabled
        self.height = height
        self.radius = radius
        self.growsVertically = growsVertically
        self.content = content()
    }

    var body: some View {
        HStack(spacing: 0) {
            content
        }
        .frame(minHeight: height, maxHeight: growsVertically ? nil : height)
        .background(isDisabled ? AikuColor.surface2 : AikuColor.surface, in: RoundedRectangle(cornerRadius: radius))
        .clipShape(RoundedRectangle(cornerRadius: radius))
        .overlay(RoundedRectangle(cornerRadius: radius).strokeBorder(borderColor))
        .overlay(
            RoundedRectangle(cornerRadius: radius + 3)
                .strokeBorder(AikuColor.primary.opacity(isFocused && !hasError ? 0.25 : 0), lineWidth: 3)
                .padding(-3)
        )
    }

    private var borderColor: Color {
        if hasError { return AikuColor.danger }
        if isFocused { return AikuColor.primary }
        return isDisabled ? AikuColor.border : AikuColor.borderStrong
    }
}

struct AikuTextField: View {
    let label: String
    @Binding var text: String
    var placeholder = ""
    var error: String?
    var isDisabled = false
    var keyboard: UIKeyboardType = .default
    @FocusState private var isFocused: Bool

    init(
        _ label: String,
        text: Binding<String>,
        placeholder: String = "",
        error: String? = nil,
        isDisabled: Bool = false,
        keyboard: UIKeyboardType = .default
    ) {
        self.label = label
        _text = text
        self.placeholder = placeholder
        self.error = error
        self.isDisabled = isDisabled
        self.keyboard = keyboard
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            FieldLabel(label, isDisabled: isDisabled)
            FieldBox(isFocused: isFocused, hasError: error != nil, isDisabled: isDisabled) {
                TextField(placeholder, text: $text)
                    .font(.aiku(16))
                    .foregroundStyle(isDisabled ? AikuColor.text4 : AikuColor.text)
                    .keyboardType(keyboard)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .focused($isFocused)
                    .disabled(isDisabled)
                    .padding(.horizontal, 12)
            }
            if let error {
                FieldError(error)
            }
        }
    }
}

struct AikuTextArea: View {
    let label: String
    @Binding var text: String
    var placeholder = ""
    var minHeight: CGFloat = 88
    @FocusState private var isFocused: Bool

    init(_ label: String, text: Binding<String>, placeholder: String = "", minHeight: CGFloat = 88) {
        self.label = label
        _text = text
        self.placeholder = placeholder
        self.minHeight = minHeight
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            FieldLabel(label)
            FieldBox(isFocused: isFocused, height: minHeight, growsVertically: true) {
                TextField(placeholder, text: $text, axis: .vertical)
                    .font(.aiku(16))
                    .foregroundStyle(AikuColor.text)
                    .lineLimit(3...8)
                    .focused($isFocused)
                    .padding(12)
                    .frame(maxHeight: .infinity, alignment: .top)
            }
        }
    }
}

struct SearchField: View {
    @Binding var text: String
    var placeholder = "Search"
    var onScan: (() -> Void)?
    var onSubmit: (() -> Void)?
    @FocusState private var isFocused: Bool

    init(text: Binding<String>, placeholder: String = "Search", onScan: (() -> Void)? = nil, onSubmit: (() -> Void)? = nil) {
        _text = text
        self.placeholder = placeholder
        self.onScan = onScan
        self.onSubmit = onSubmit
    }

    var body: some View {
        FieldBox(isFocused: isFocused, height: 46) {
            AppIcon.search.image
                .font(.system(size: 17))
                .foregroundStyle(AikuColor.text4)
                .padding(.leading, 14)
                .accessibilityHidden(true)
            TextField(placeholder, text: $text)
                .font(.aiku(15))
                .foregroundStyle(AikuColor.text)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .submitLabel(.search)
                .focused($isFocused)
                .onSubmit { onSubmit?() }
                .padding(.horizontal, 10)
            if !text.isEmpty {
                Button {
                    text = ""
                } label: {
                    AppIcon.close.image
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(AikuColor.text4)
                        .frame(width: 32, height: 44)
                }
                .buttonStyle(.plain)
                .accessibilityLabel("Clear search")
            }
            if let onScan {
                ScanButton(size: 36, action: onScan)
                    .padding(.trailing, 5)
            }
        }
    }
}

struct QuantityStepper: View {
    @Binding var value: Int
    var range: ClosedRange<Int> = 0...99999
    var step = 1

    init(value: Binding<Int>, range: ClosedRange<Int> = 0...99999, step: Int = 1) {
        _value = value
        self.range = range
        self.step = step
    }

    var body: some View {
        FieldBox {
            stepButton("−", label: "Decrease", isEnabled: value > range.lowerBound) {
                value = max(range.lowerBound, value - step)
            }
            Rectangle().fill(AikuColor.border).frame(width: 1)
            Text("\(value)")
                .font(.aiku(18, .semibold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .frame(maxWidth: .infinity)
                .accessibilityLabel("Quantity \(value)")
            Rectangle().fill(AikuColor.border).frame(width: 1)
            stepButton("+", label: "Increase", isEnabled: value < range.upperBound) {
                value = min(range.upperBound, value + step)
            }
        }
    }

    private func stepButton(_ symbol: String, label: String, isEnabled: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(symbol)
                .font(.aiku(22))
                .foregroundStyle(isEnabled ? AikuColor.text2 : AikuColor.faint)
                .frame(width: 56)
                .frame(maxHeight: .infinity)
                .background(AikuColor.surface2)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .disabled(!isEnabled)
        .accessibilityLabel(label)
    }
}

struct RadioCard<Trailing: View>: View {
    let title: String
    var subtitle: String?
    let isSelected: Bool
    let action: () -> Void
    @ViewBuilder var trailing: Trailing

    init(
        _ title: String,
        subtitle: String? = nil,
        isSelected: Bool,
        action: @escaping () -> Void,
        @ViewBuilder trailing: () -> Trailing
    ) {
        self.title = title
        self.subtitle = subtitle
        self.isSelected = isSelected
        self.action = action
        self.trailing = trailing()
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    if let subtitle {
                        Text(subtitle)
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
                trailing
                if isSelected {
                    AppIcon.check.image
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(AikuColor.link)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 8)
            .frame(minHeight: 56)
            .background(isSelected ? AikuColor.soft : AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
            .overlay(
                RoundedRectangle(cornerRadius: 10)
                    .strokeBorder(isSelected ? AikuColor.primary : AikuColor.border, lineWidth: isSelected ? 2 : 1)
            )
            .contentShape(RoundedRectangle(cornerRadius: 10))
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

extension RadioCard where Trailing == EmptyView {
    init(_ title: String, subtitle: String? = nil, isSelected: Bool, action: @escaping () -> Void) {
        self.init(title, subtitle: subtitle, isSelected: isSelected, action: action) { EmptyView() }
    }
}

struct CheckboxRow: View {
    let title: String
    var subtitle: String?
    @Binding var isOn: Bool

    init(_ title: String, subtitle: String? = nil, isOn: Binding<Bool>) {
        self.title = title
        self.subtitle = subtitle
        _isOn = isOn
    }

    var body: some View {
        Button {
            isOn.toggle()
        } label: {
            HStack(spacing: 10) {
                Image(systemName: isOn ? "checkmark.square.fill" : "square")
                    .font(.system(size: 20))
                    .foregroundStyle(isOn ? AikuColor.primary : AikuColor.borderStrong)
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text2)
                    if let subtitle {
                        Text(subtitle)
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                .multilineTextAlignment(.leading)
                Spacer(minLength: 0)
            }
            .frame(minHeight: 44)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isOn ? .isSelected : [])
    }
}

struct ToggleRow: View {
    let title: String
    var subtitle: String?
    @Binding var isOn: Bool

    init(_ title: String, subtitle: String? = nil, isOn: Binding<Bool>) {
        self.title = title
        self.subtitle = subtitle
        _isOn = isOn
    }

    var body: some View {
        Toggle(isOn: $isOn) {
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(AikuColor.text)
                if let subtitle {
                    Text(subtitle)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
            }
        }
        .tint(AikuColor.primary)
        .padding(.horizontal, 14)
        .padding(.vertical, 8)
        .frame(minHeight: 56)
    }
}

struct SegmentedControl<Option: Hashable>: View {
    let options: [Option]
    @Binding var selection: Option
    let title: (Option) -> String
    var icon: ((Option) -> AppIcon?)?

    init(options: [Option], selection: Binding<Option>, title: @escaping (Option) -> String, icon: ((Option) -> AppIcon?)? = nil) {
        self.options = options
        _selection = selection
        self.title = title
        self.icon = icon
    }

    var body: some View {
        HStack(spacing: 4) {
            ForEach(options, id: \.self) { option in
                let isSelected = option == selection
                Button {
                    selection = option
                } label: {
                    HStack(spacing: 6) {
                        if let symbol = icon?(option) {
                            symbol.image.font(.system(size: 15))
                        }
                        Text(title(option))
                            .font(.aiku(14, .semibold))
                            .lineLimit(1)
                            .minimumScaleFactor(0.8)
                    }
                    .foregroundStyle(isSelected ? AikuColor.text : AikuColor.text3)
                    .frame(maxWidth: .infinity, minHeight: 44)
                    .background(isSelected ? AikuColor.surface : .clear, in: RoundedRectangle(cornerRadius: 6))
                    .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(isSelected ? AikuColor.borderStrong : .clear))
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityAddTraits(isSelected ? .isSelected : [])
            }
        }
        .padding(4)
        .background(AikuColor.bg, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
    }
}
