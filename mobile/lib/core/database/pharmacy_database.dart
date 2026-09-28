import 'package:businessos_pharmacy/core/database/local_database_scope.dart';
import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

part 'pharmacy_database.g.dart';

class AppMetadata extends Table {
  TextColumn get key => text()();
  TextColumn get value => text()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{key};
}

class LocalMedicines extends Table {
  TextColumn get id => text()();
  TextColumn get medicineCode => text().nullable()();
  TextColumn get brandName => text()();
  TextColumn get genericName => text().nullable()();
  TextColumn get saleUnit => text().withDefault(const Constant<String>('unit'))();
  BoolColumn get isActive => boolean().withDefault(const Constant<bool>(true))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant<bool>(false))();
  DateTimeColumn get serverUpdatedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{id};
}

class LocalInventoryBatches extends Table {
  TextColumn get id => text()();
  TextColumn get medicineId => text()();
  TextColumn get stockLocationId => text()();
  TextColumn get batchNumber => text()();
  DateTimeColumn get expiresAt => dateTime().nullable()();
  TextColumn get availableQuantity => text().withDefault(const Constant<String>('0'))();
  TextColumn get salePrice => text().withDefault(const Constant<String>('0'))();
  TextColumn get purchaseCost => text().withDefault(const Constant<String>('0'))();
  TextColumn get status => text().withDefault(const Constant<String>('active'))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant<bool>(false))();
  DateTimeColumn get serverUpdatedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{id};
}

class LocalCustomers extends Table {
  TextColumn get id => text()();
  TextColumn get name => text()();
  TextColumn get phone => text().nullable()();
  TextColumn get balance => text().withDefault(const Constant<String>('0'))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant<bool>(false))();
  DateTimeColumn get serverUpdatedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{id};
}

class LocalSales extends Table {
  TextColumn get localId => text()();
  TextColumn get serverId => text().nullable()();
  TextColumn get customerId => text().nullable()();
  TextColumn get stockLocationId => text()();
  TextColumn get businessDate => text()();
  TextColumn get currency => text().withDefault(const Constant<String>('AFN'))();
  TextColumn get status => text().withDefault(const Constant<String>('draft'))();
  TextColumn get syncState => text().withDefault(const Constant<String>('local'))();
  TextColumn get subtotal => text().withDefault(const Constant<String>('0'))();
  TextColumn get discountAmount => text().withDefault(const Constant<String>('0'))();
  TextColumn get total => text().withDefault(const Constant<String>('0'))();
  TextColumn get tenderedAmount => text().withDefault(const Constant<String>('0'))();
  TextColumn get changeAmount => text().withDefault(const Constant<String>('0'))();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();
  DateTimeColumn get completedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{localId};
}

class LocalSaleLines extends Table {
  TextColumn get localId => text()();
  TextColumn get saleLocalId => text()();
  TextColumn get medicineId => text()();
  TextColumn get batchId => text().nullable()();
  TextColumn get quantity => text()();
  TextColumn get unitPrice => text()();
  TextColumn get discountAmount => text().withDefault(const Constant<String>('0'))();
  TextColumn get lineTotal => text()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{localId};
}

class LocalSalePayments extends Table {
  TextColumn get localId => text()();
  TextColumn get saleLocalId => text()();
  TextColumn get method => text()();
  TextColumn get amount => text()();
  TextColumn get reference => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{localId};
}

class SyncOutboxEntries extends Table {
  TextColumn get idempotencyKey => text()();
  TextColumn get aggregateType => text()();
  TextColumn get aggregateId => text()();
  TextColumn get eventType => text()();
  TextColumn get payloadJson => text()();
  TextColumn get status => text().withDefault(const Constant<String>('pending'))();
  IntColumn get attemptCount => integer().withDefault(const Constant<int>(0))();
  DateTimeColumn get nextAttemptAt => dateTime().nullable()();
  TextColumn get lastError => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{idempotencyKey};
}

class SyncCheckpoints extends Table {
  TextColumn get stream => text()();
  TextColumn get cursor => text().nullable()();
  DateTimeColumn get lastSyncedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{stream};
}

@DriftDatabase(
  tables: <Type>[
    AppMetadata,
    LocalMedicines,
    LocalInventoryBatches,
    LocalCustomers,
    LocalSales,
    LocalSaleLines,
    LocalSalePayments,
    SyncOutboxEntries,
    SyncCheckpoints,
  ],
)
final class PharmacyDatabase extends _$PharmacyDatabase {
  PharmacyDatabase(super.e);

  PharmacyDatabase.forScope(LocalDatabaseScope scope)
      : super(
          driftDatabase(
            name: scope.databaseName,
            native: DriftNativeOptions(shareAcrossIsolates: true),
          ),
        );

  @override
  int get schemaVersion => 1;

  @override
  MigrationStrategy get migration => MigrationStrategy(
        beforeOpen: (OpeningDetails details) async {
          await customStatement('PRAGMA foreign_keys = ON');
          await customStatement('PRAGMA journal_mode = WAL');
        },
      );
}
