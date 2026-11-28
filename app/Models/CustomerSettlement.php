<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class CustomerSettlement extends Model {
    use HasUlids;
    protected $fillable = ['sale_id','method','amount','currency','business_date','paid_at','created_by','reference','idempotency_key'];
    protected function casts(): array { return ['amount'=>'decimal:4','paid_at'=>'datetime','business_date'=>'date']; }
    public function paymentJournal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(JournalEntry::class, 'source_id')->where('source_type', self::class)->where('source_event','paid');
    }
}
