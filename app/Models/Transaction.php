<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $table = "transactions";
    protected $fillable = [
        "transaction_id",
        "product_code",
        "user_id",
        "ok_id",
        "destination",
        "sn_id",
        "price",
        "supply_price",
        "status",
        "time"
    ];
    protected $primaryKey = "transaction_id";
    public $incrementing = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_code', 'product_code');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
