import Foundation

struct UploadFile: Equatable, Identifiable, Sendable {
    let id: UUID
    let name: String
    let mimeType: String
    let data: Data

    init(name: String, mimeType: String, data: Data) {
        id = UUID()
        self.name = name
        self.mimeType = mimeType
        self.data = data
    }

    static let maxCount = 3
    static let maxBytes = 5 * 1024 * 1024
    static let allowedMimeTypes = ["application/pdf", "image/jpeg", "image/png"]

    static func mimeType(forExtension fileExtension: String) -> String? {
        switch fileExtension.lowercased() {
        case "pdf": "application/pdf"
        case "jpg", "jpeg": "image/jpeg"
        case "png": "image/png"
        default: nil
        }
    }

    static func problem(adding new: [UploadFile], to current: [UploadFile]) -> String? {
        if current.count + new.count > maxCount { return "You can add up to 3 files." }
        if new.contains(where: { $0.data.count > maxBytes }) { return "Each file must be 5 MB or smaller." }
        if new.contains(where: { !allowedMimeTypes.contains($0.mimeType) }) { return "Use PDF, JPG or PNG files." }
        return nil
    }
}

struct MultipartForm: Sendable {
    let boundary: String
    private(set) var fields: [(String, String)] = []
    private(set) var files: [(String, UploadFile)] = []

    init(boundary: String = "aiku-\(UUID().uuidString)") {
        self.boundary = boundary
    }

    var contentType: String { "multipart/form-data; boundary=\(boundary)" }

    mutating func add(_ name: String, _ value: String?) {
        guard let value else { return }
        fields.append((name, value))
    }

    mutating func add(files: [UploadFile], as name: String) {
        for file in files {
            self.files.append((name, file))
        }
    }

    var data: Data {
        var body = Data()
        func line(_ text: String) { body.append(Data((text + "\r\n").utf8)) }
        for (name, value) in fields {
            line("--\(boundary)")
            line("Content-Disposition: form-data; name=\"\(name)\"")
            line("")
            line(value)
        }
        for (name, file) in files {
            let safeName = file.name.replacingOccurrences(of: "\"", with: "")
            line("--\(boundary)")
            line("Content-Disposition: form-data; name=\"\(name)\"; filename=\"\(safeName)\"")
            line("Content-Type: \(file.mimeType)")
            line("")
            body.append(file.data)
            line("")
        }
        line("--\(boundary)--")
        return body
    }

    func endpoint(_ path: String, method: HTTPMethod = .post) -> Endpoint {
        Endpoint(path: path, method: method, body: data, contentType: contentType)
    }
}

struct ClockingAttachment: Decodable, Equatable, Identifiable, Sendable {
    let id: Int
    let name: String
    let url: String

    var ulid: String? {
        URL(string: url)?.lastPathComponent
    }
}

extension APIClient {
    func downloadClockingAttachment(_ attachment: ClockingAttachment) async throws -> URL {
        guard let ulid = attachment.ulid, !ulid.isEmpty else { throw APIError.unexpectedBody }
        let data = try await send(Endpoint(path: "mobile/clocking/attachments/\(ulid)", queryItems: [URLQueryItem(name: "inline", value: "1")]))
        let folder = FileManager.default.temporaryDirectory.appending(path: "aiku-attachments", directoryHint: .isDirectory)
        try FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
        let file = folder.appending(path: "\(attachment.id)-\(attachment.name)")
        try data.write(to: file, options: .atomic)
        return file
    }
}
