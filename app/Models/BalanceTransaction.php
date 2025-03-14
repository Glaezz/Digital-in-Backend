<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceTransaction extends Model
{
    use HasFactory;
    protected $table = "balance_transactions";

    protected $fillable = [
        "midtrans_id",
        "user_id",
        "payment_method_id",
        "amount",
        "status",
        "expiry_time",
        "payment_details"
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, "payment_method_id", "id");
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
