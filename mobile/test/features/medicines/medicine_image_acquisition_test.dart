import 'package:businessos_pharmacy/features/medicines/domain/medicine_image_acquisition.dart';
import 'package:flutter_test/flutter_test.dart';

class FakeImageSource implements LocalMedicineImageSource {
  FakeImageSource({this.cameraPath, this.galleryPath});
  final String? cameraPath;
  final String? galleryPath;
  int cameraCalls = 0;
  int galleryCalls = 0;

  @override
  Future<String?> captureImage() async {
    cameraCalls++;
    return cameraPath;
  }

  @override
  Future<String?> chooseLocalImage() async {
    galleryCalls++;
    return galleryPath;
  }
}

void main() {
  test('camera returns local image without calling gallery', () async {
    final source = FakeImageSource(cameraPath: '/local/medicine.jpg');
    final result = await MedicineImageAcquisition(
      source,
    ).acquire(MedicineImageOrigin.camera);
    expect(result?.localPath, '/local/medicine.jpg');
    expect(result?.origin, MedicineImageOrigin.camera);
    expect(source.cameraCalls, 1);
    expect(source.galleryCalls, 0);
  });

  test('cancelled gallery selection does not create a draft', () async {
    final source = FakeImageSource();
    expect(
      await MedicineImageAcquisition(
        source,
      ).acquire(MedicineImageOrigin.gallery),
      isNull,
    );
  });

  test('rejects remote image URLs', () async {
    final source = FakeImageSource(
      galleryPath: 'https://example.com/medicine.jpg',
    );
    await expectLater(
      MedicineImageAcquisition(source).acquire(MedicineImageOrigin.gallery),
      throwsStateError,
    );
  });

  test('rejects empty image paths', () async {
    final source = FakeImageSource(cameraPath: '  ');
    await expectLater(
      MedicineImageAcquisition(source).acquire(MedicineImageOrigin.camera),
      throwsStateError,
    );
  });
}
