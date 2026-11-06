Pod::Spec.new do |s|
  s.name             = 'offline_medicine_ocr'
  s.version          = '0.1.0'
  s.summary          = 'Local-only medicine label OCR using Apple Vision.'
  s.description      = 'Recognizes text from local images on-device without hosted OCR.'
  s.homepage         = 'https://darmaltoon.com'
  s.license          = { :type => 'MIT' }
  s.author           = { 'Darmaltoon' => 'support@darmaltoon.com' }
  s.source           = { :path => '.' }
  s.source_files     = 'Classes/**/*'
  s.dependency 'Flutter'
  s.platform         = :ios, '13.0'
  s.swift_version    = '5.0'
end
