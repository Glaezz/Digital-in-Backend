<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        "product_code",
        "detail",
        "product",
        "category_id",
        "supply_price",
        "price",
        "status",
    ];
    protected $primaryKey = "product_code";
    public $incrementing = false;
    use HasFactory;

    function category()
    {
        return $this->belongsTo(Category::class, "category_id", "id");
    }
}
