import Flutter
import Foundation
import UIKit
import Vision

public class OfflineMedicineOcrPlugin: NSObject, FlutterPlugin {
    public static func register(with registrar: FlutterPluginRegistrar) {
        let channel = FlutterMethodChannel(
            name: "darmaltoon/offline_medicine_ocr",
            binaryMessenger: registrar.messenger()
        )
        registrar.addMethodCallDelegate(OfflineMedicineOcrPlugin(), channel: channel)
    }

    public func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
        switch call.method {
        case "isAvailable":
            result(true)
        case "recognizeLocalImage":
            guard let arguments = call.arguments as? [String: Any],
                  let path = arguments["path"] as? String,
                  !path.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
                result(FlutterError(code: "invalid_path", message: "Local image path required", details: nil))
                return
            }
            recognize(path: path, result: result)
        default:
            result(FlutterMethodNotImplemented)
        }
    }

    private func recognize(path: String, result: @escaping FlutterResult) {
        let url: URL
        if path.hasPrefix("file://") {
            guard let parsed = URL(string: path), parsed.isFileURL,
                  parsed.host == nil || parsed.host == "" else {
                result(FlutterError(code: "invalid_path", message: "Invalid local file URL", details: nil))
                return
            }
            url = parsed
        } else {
            guard path.hasPrefix("/"), !path.contains("://") else {
                result(FlutterError(code: "invalid_path", message: "Remote images are forbidden", details: nil))
                return
            }
            url = URL(fileURLWithPath: path)
        }

        guard FileManager.default.isReadableFile(atPath: url.path) else {
            result(FlutterError(code: "invalid_path", message: "Local image is unreadable", details: nil))
            return
        }

        DispatchQueue.global(qos: .userInitiated).async {
            // Vision's language coverage depends on the installed iOS version.
            // Run separate supported-script passes to avoid English dominating
            // mixed English/Dari/Pashto pharmaceutical packaging.
            let probe = VNRecognizeTextRequest()
            probe.recognitionLevel = .accurate
            let supported = (try? probe.supportedRecognitionLanguages()) ?? []
            let preferred = ["fa-AF", "fa-IR", "ps-AF", "en-US"]
            let available = preferred.filter { supported.contains($0) }

            // No language pack downloads or network requests are made.
            // When Persian/Pashto are unavailable, Vision's default OCR
            // remains useful for Latin labels but is not claimed to support
            // those scripts.
            // Retain default Vision recognition for mixed-script labels.
            let passes: [String?] = [nil] + available.map { Optional($0) }
            var merged: [String] = []
            var seen = Set<String>()
            var succeeded = false

            for language in passes {
                let request = VNRecognizeTextRequest()
                request.recognitionLevel = .accurate
                request.usesLanguageCorrection = true
                if let language = language {
                    request.recognitionLanguages = [language]
                    request.automaticallyDetectsLanguage = false
                }
                do {
                    let handler = VNImageRequestHandler(url: url, options: [:])
                    try handler.perform([request])
                    succeeded = true
                    for observation in request.results ?? [] {
                        guard let line = observation.topCandidates(1).first?.string
                            .trimmingCharacters(in: .whitespacesAndNewlines),
                            !line.isEmpty else { continue }
                        let key = line.folding(
                            options: [.caseInsensitive, .diacriticInsensitive],
                            locale: Locale(identifier: "en_US_POSIX")
                        )
                        if seen.insert(key).inserted {
                            merged.append(line)
                        }
                    }
                } catch {
                    // A failed language pass must not discard successful passes.
                    continue
                }
            }

            DispatchQueue.main.async {
                if succeeded {
                    result(merged)
                } else {
                    result(FlutterError(
                        code: "recognition_failed",
                        message: "On-device Vision text recognition failed",
                        details: nil
                    ))
                }
            }
        }
    }
}
