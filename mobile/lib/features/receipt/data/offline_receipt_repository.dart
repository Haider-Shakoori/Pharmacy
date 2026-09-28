import 'package:businessos_pharmacy/core/database/pharmacy_database.dart';
import 'package:businessos_pharmacy/core/decimal/fixed_decimal.dart';
import 'package:businessos_pharmacy/features/receipt/domain/offline_receipt.dart';
import 'package:drift/drift.dart';

class OfflineReceiptRepository {
  const OfflineReceiptRepository(this._database);

  final PharmacyDatabase _database;

  Future<OfflineReceipt?> find(String saleLocalId) async {
    final QueryRow? sale = await _database
        .customSelect(
          '''
          SELECT sale.*, customer.name AS customer_name
          FROM local_sales sale
          LEFT JOIN local_customers customer
            ON customer.id = sale.customer_id
          WHERE sale.local_id = ?
            AND sale.status = 'completed'
          LIMIT 1
          ''',
          variables: <Variable<Object>>[Variable<String>(saleLocalId)],
        )
        .getSingleOrNull();

    if (sale == null) {
      return null;
    }

    return _hydrate(sale);
  }

  Future<List<OfflineReceipt>> recentCompleted({int limit = 5}) async {
    final List<QueryRow> rows = await _database
        .customSelect(
          '''
          SELECT sale.*, customer.name AS customer_name
          FROM local_sales sale
          LEFT JOIN local_customers customer
            ON customer.id = sale.customer_id
          WHERE sale.status = 'completed'
          ORDER BY sale.completed_at DESC, sale.local_id DESC
          LIMIT ?
          ''',
          variables: <Variable<Object>>[Variable<int>(limit)],
        )
        .get();

    final List<OfflineReceipt> receipts = <OfflineReceipt>[];
    for (final QueryRow row in rows) {
      receipts.add(await _hydrate(row));
    }

    return receipts;
  }

  Future<OfflineReceipt> _hydrate(QueryRow sale) async {
    final String saleLocalId = sale.read<String>('local_id');

    final List<QueryRow> lineRows = await _database
        .customSelect(
          '''
          SELECT
            line.quantity,
            line.unit_price,
            line.discount_amount,
            line.line_total,
            medicine.brand_name,
            medicine.sale_unit
          FROM local_sale_lines line
          INNER JOIN local_medicines medicine
            ON medicine.id = line.medicine_id
          WHERE line.sale_local_id = ?
          ORDER BY line.local_id
          ''',
          variables: <Variable<Object>>[Variable<String>(saleLocalId)],
        )
        .get();

    final List<QueryRow> paymentRows = await _database
        .customSelect(
          '''
          SELECT method, amount, reference
          FROM local_sale_payments
          WHERE sale_local_id = ?
          ORDER BY local_id
          ''',
          variables: <Variable<Object>>[Variable<String>(saleLocalId)],
        )
        .get();

    final int completedSeconds =
        sale.readNullable<int>('completed_at') ?? sale.read<int>('created_at');

    return OfflineReceipt(
      saleLocalId: saleLocalId,
      serverId: sale.readNullable<String>('server_id'),
      stockLocationId: sale.read<String>('stock_location_id'),
      businessDate: sale.read<String>('business_date'),
      syncState: sale.read<String>('sync_state'),
      customerName: sale.readNullable<String>('customer_name'),
      subtotal: FixedDecimal.parse(sale.read<String>('subtotal')),
      discountAmount: FixedDecimal.parse(sale.read<String>('discount_amount')),
      total: FixedDecimal.parse(sale.read<String>('total')),
      tenderedAmount: FixedDecimal.parse(sale.read<String>('tendered_amount')),
      changeAmount: FixedDecimal.parse(sale.read<String>('change_amount')),
      completedAt: DateTime.fromMillisecondsSinceEpoch(
        completedSeconds * 1000,
      ),
      lines: lineRows
          .map(
            (QueryRow row) => OfflineReceiptLine(
              medicineName: row.read<String>('brand_name'),
              saleUnit: row.read<String>('sale_unit'),
              quantity: FixedDecimal.parse(row.read<String>('quantity')),
              unitPrice: FixedDecimal.parse(row.read<String>('unit_price')),
              discountAmount: FixedDecimal.parse(
                row.read<String>('discount_amount'),
              ),
              lineTotal: FixedDecimal.parse(row.read<String>('line_total')),
            ),
          )
          .toList(growable: false),
      payments: paymentRows
          .map(
            (QueryRow row) => OfflineReceiptPayment(
              method: row.read<String>('method'),
              amount: FixedDecimal.parse(row.read<String>('amount')),
              reference: row.readNullable<String>('reference'),
            ),
          )
          .toList(growable: false),
    );
  }
}
