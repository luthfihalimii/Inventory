<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ItemIssueReport extends Model
{
    protected $fillable = ['user_id','inventory_item_id','inventory_unit_id','judul','deskripsi','status','ditangani_oleh','catatan_tindak_lanjut','waktu_selesai'];
    protected $casts = ['waktu_selesai'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class,'inventory_item_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(InventoryUnit::class,'inventory_unit_id'); }
    public function handler(): BelongsTo { return $this->belongsTo(User::class,'ditangani_oleh'); }
}
