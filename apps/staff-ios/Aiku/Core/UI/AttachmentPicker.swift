import PhotosUI
import SwiftUI
import UniformTypeIdentifiers

struct AttachmentPicker: View {
    let title: String
    @Binding var files: [UploadFile]
    @State private var photoItems: [PhotosPickerItem] = []
    @State private var isImporting = false
    @State private var problem: String?

    init(_ title: String = "Supporting document (optional)", files: Binding<[UploadFile]>) {
        self.title = title
        _files = files
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            FieldLabel(title)
            if !files.isEmpty {
                VStack(spacing: 0) {
                    ForEach(Array(files.enumerated()), id: \.element.id) { index, file in
                        if index > 0 { RowDivider() }
                        HStack(spacing: 10) {
                            (file.mimeType == "application/pdf" ? AppIcon.document : AppIcon.photo).image
                                .foregroundStyle(AikuColor.text3)
                                .accessibilityHidden(true)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(file.name)
                                    .font(.aiku(14, .semibold))
                                    .foregroundStyle(AikuColor.text)
                                    .lineLimit(1)
                                    .truncationMode(.middle)
                                Text(ByteCountFormatter.string(fromByteCount: Int64(file.data.count), countStyle: .file))
                                    .font(.aiku(12))
                                    .foregroundStyle(AikuColor.text4)
                            }
                            .frame(maxWidth: .infinity, alignment: .leading)
                            IconButton(.close, label: "Remove \(file.name)") {
                                files.removeAll { $0.id == file.id }
                                problem = nil
                            }
                        }
                        .padding(.horizontal, 12)
                        .frame(minHeight: 52)
                    }
                }
                .cardSurface(radius: 8)
            }
            if files.count < UploadFile.maxCount {
                HStack(spacing: 8) {
                    PhotosPicker(selection: $photoItems, maxSelectionCount: UploadFile.maxCount - files.count, matching: .images) {
                        Label("Add photo", systemImage: AppIcon.photo.rawValue)
                            .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(AikuButtonStyle(kind: .tertiary, size: .small))
                    Button {
                        isImporting = true
                    } label: {
                        Label("Add file", systemImage: AppIcon.document.rawValue)
                            .frame(maxWidth: .infinity)
                    }
                    .buttonStyle(AikuButtonStyle(kind: .tertiary, size: .small))
                }
            }
            if let problem {
                FieldError(problem)
            } else {
                Text("PDF, JPG or PNG · up to 3 files, 5 MB each")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
        }
        .onChange(of: photoItems) { _, items in
            guard !items.isEmpty else { return }
            Task {
                var added: [UploadFile] = []
                for (offset, item) in items.enumerated() {
                    guard let data = try? await item.loadTransferable(type: Data.self) else { continue }
                    let jpeg = UIImage(data: data)?.jpegData(compressionQuality: 0.8) ?? data
                    added.append(UploadFile(name: "photo-\(offset + 1 + files.count).jpg", mimeType: "image/jpeg", data: jpeg))
                }
                photoItems = []
                add(added)
            }
        }
        .fileImporter(isPresented: $isImporting, allowedContentTypes: [.pdf, .jpeg, .png], allowsMultipleSelection: true) { result in
            guard case .success(let urls) = result else { return }
            var added: [UploadFile] = []
            for url in urls {
                let isScoped = url.startAccessingSecurityScopedResource()
                defer { if isScoped { url.stopAccessingSecurityScopedResource() } }
                guard let data = try? Data(contentsOf: url), let mime = UploadFile.mimeType(forExtension: url.pathExtension) else { continue }
                added.append(UploadFile(name: url.lastPathComponent, mimeType: mime, data: data))
            }
            add(added)
        }
    }

    private func add(_ new: [UploadFile]) {
        if let issue = UploadFile.problem(adding: new, to: files) {
            problem = issue
            return
        }
        problem = nil
        files.append(contentsOf: new)
    }
}
