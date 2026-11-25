<?php
namespace App\Http\Controllers;
use App\Models\ItemIssueReport;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
class ItemIssueReportController extends Controller
{
 public function index(Request $r): View { $q=ItemIssueReport::with(['user.member','item','unit','handler.member'])->latest(); if(!$r->user()->roles()->whereIn('nama',['ADMIN','SUPER_ADMIN'])->exists()) $q->where('user_id',$r->user()->id); return view('item-issues.index',['reports'=>$q->paginate(20)]); }
 public function create(): View { return view('item-issues.create',['items'=>InventoryItem::where('status','ACTIVE')->orderBy('nama')->get(),'units'=>InventoryUnit::with('inventoryItem')->whereIn('status',['AVAILABLE','BORROWED','MAINTENANCE'])->get()]); }
 public function store(Request $r): RedirectResponse { $d=$r->validate(['inventory_item_id'=>['required','exists:inventory_items,id'],'inventory_unit_id'=>['nullable','exists:inventory_units,id'],'judul'=>['required','string','max:160'],'deskripsi'=>['required','string','max:3000']]); if(!empty($d['inventory_unit_id']) && !InventoryUnit::whereKey($d['inventory_unit_id'])->where('inventory_item_id',$d['inventory_item_id'])->exists()) return back()->withErrors(['inventory_unit_id'=>'Unit tidak sesuai dengan jenis barang.'])->withInput(); $d['user_id']=$r->user()->id; $report=ItemIssueReport::create($d); $this->writeAudit('CREATE','ItemIssueReport',$report->id,null,$report->toArray()); return redirect()->route('item-issues.index')->with('success','Laporan masalah barang berhasil dikirim.'); }
 public function update(Request $r, ItemIssueReport $itemIssue): RedirectResponse { abort_unless($r->user()->roles()->where('nama','ADMIN')->exists(),403); $d=$r->validate(['status'=>['required',Rule::in(['OPEN','IN_PROGRESS','RESOLVED','REJECTED'])],'catatan_tindak_lanjut'=>['nullable','string','max:3000']]); $d['ditangani_oleh']=$r->user()->id; $d['waktu_selesai']=in_array($d['status'],['RESOLVED','REJECTED'])?now():null; $itemIssue->update($d); $this->writeAudit('UPDATE','ItemIssueReport',$itemIssue->id,null,$itemIssue->fresh()->toArray()); return back()->with('success','Tindak lanjut laporan diperbarui.'); }
}
