@php($currentStatus = old('status', $announcement->status ?? 'DRAFT'))
@php($initialAnnouncementHtml = \App\Support\AnnouncementHtmlSanitizer::clean(old('isi', $announcement->isi ?? '')))
<div class="announcement-form-stack">
    <div class="announcement-editor-heading">
        <div class="form-grid">
            <div class="field full"><label for="announcement-title">Judul pengumuman</label><input id="announcement-title" name="judul" maxlength="180" value="{{ old('judul', $announcement->judul ?? '') }}" required></div>
            <div class="field"><label for="announcement-category">Kategori</label><select id="announcement-category" name="kategori">@foreach(['INFORMASI'=>'Informasi','OPERASIONAL'=>'Operasional','PEMELIHARAAN'=>'Pemeliharaan','KEBIJAKAN'=>'Kebijakan'] as $key=>$label)<option value="{{ $key }}" @selected(old('kategori',$announcement->kategori ?? 'INFORMASI')===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="announcement-status">Status</label><select id="announcement-status" name="status">@foreach(['DRAFT'=>'Draft','PUBLISHED'=>'Publikasikan / Jadwalkan','INACTIVE'=>'Nonaktif'] as $key=>$label)<option value="{{ $key }}" @selected($currentStatus===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="announcement-published-at">Waktu publikasi</label><input id="announcement-published-at" type="datetime-local" name="published_at" value="{{ old('published_at', isset($announcement)&&$announcement->published_at?$announcement->published_at->format('Y-m-d\TH:i'):'') }}"><small class="muted">Jika waktunya di masa depan, pengumuman otomatis menunggu jadwal.</small></div>
            <div class="field" style="justify-content:end"><label class="inline-check"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned',$announcement->is_pinned ?? false))> Sematkan di bagian atas</label></div>
        </div>
    </div>

    <div class="field">
        <div class="announcement-toolbar-label"><label>Isi pengumuman</label><span id="announcement-word-stats" class="muted">0 kata · 0 karakter · 1 menit baca</span></div>
        <div id="announcement-editor" class="announcement-editor"></div>
        <textarea id="announcement-content" name="isi" hidden>{{ $initialAnnouncementHtml }}</textarea>
        <div class="announcement-editor-footer"><small class="muted">Heading 1–6, format teks, daftar bertingkat, kutipan, tautan, dan gambar.</small><button type="button" class="btn btn-secondary btn-sm" id="announcement-preview-toggle"><i class="fa-regular fa-eye"></i> Tampilkan preview</button></div>
    </div>
    <section id="announcement-preview" class="announcement-preview" hidden>
        <div class="announcement-preview-head"><strong>Pratinjau pengumuman</strong><span class="muted">Tampilan anggota</span></div>
        <div id="announcement-preview-content" class="announcement-rendered"></div>
    </section>
</div>
@once
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<style>
.announcement-form-stack{display:grid;gap:18px}.announcement-editor-heading{position:sticky;top:66px;z-index:8;background:var(--surface);padding:12px 0 4px;border-bottom:1px solid var(--line)}.announcement-toolbar-label{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px}.announcement-editor{background:var(--surface);border:1px solid var(--line);border-radius:10px;overflow:visible}.announcement-editor .ql-toolbar.ql-snow{position:sticky;top:154px;z-index:7;border:0;border-bottom:1px solid var(--line);border-radius:10px 10px 0 0;background:var(--surface-2);padding:9px;box-shadow:0 3px 8px rgba(15,23,42,.05)}.announcement-editor .ql-container.ql-snow{border:0;font:14px/1.7 Inter,system-ui,sans-serif}.announcement-editor .ql-editor{min-height:360px;max-height:65vh;overflow-y:auto;color:var(--text);padding:18px}.announcement-editor .ql-editor.ql-blank::before{color:var(--muted);font-style:normal}.announcement-editor .ql-snow .ql-picker{color:var(--text)}.announcement-editor .ql-snow .ql-stroke{stroke:var(--muted)}.announcement-editor .ql-snow .ql-fill{fill:var(--muted)}.announcement-editor .ql-snow .ql-picker-options{background:var(--surface);border-color:var(--line);box-shadow:var(--shadow)}.announcement-editor .ql-snow .ql-picker.ql-expanded .ql-picker-options{z-index:10}.announcement-editor .ql-editor blockquote,.announcement-rendered blockquote{border-left:4px solid var(--teal);background:var(--teal-soft);padding:14px 18px;margin:14px 0;border-radius:0 9px 9px 0;color:var(--text);font-style:italic}.announcement-editor .ql-editor blockquote p{margin:0}.announcement-editor-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:9px;flex-wrap:wrap}.announcement-preview{border:1px solid var(--line);border-radius:12px;background:var(--surface);padding:20px}.announcement-preview-head{display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding-bottom:12px;margin-bottom:14px}.announcement-rendered{line-height:1.8;overflow-wrap:anywhere}.announcement-rendered h1{font-size:2em;line-height:1.2;margin:.7em 0 .4em}.announcement-rendered h2{font-size:1.65em;line-height:1.25;margin:.7em 0 .4em}.announcement-rendered h3{font-size:1.4em;margin:.7em 0 .4em}.announcement-rendered h4{font-size:1.2em;margin:.7em 0 .4em}.announcement-rendered h5{font-size:1.05em;margin:.7em 0 .4em}.announcement-rendered h6{font-size:.95em;margin:.7em 0 .4em}.announcement-rendered img{max-width:100%;height:auto;border-radius:9px}.announcement-rendered a{color:var(--primary);text-decoration:underline;overflow-wrap:anywhere}.announcement-rendered li{padding-left:4px}.announcement-rendered li.ql-indent-1{margin-left:1.5em}.announcement-rendered li.ql-indent-2{margin-left:3em}.announcement-rendered li.ql-indent-3{margin-left:4.5em}.announcement-rendered li.ql-indent-4{margin-left:6em}.announcement-rendered li[data-list="bullet"]{list-style-type:disc}.announcement-rendered li[data-list="ordered"]{list-style-type:decimal}.announcement-rendered ol,.announcement-rendered ul{padding-left:1.6em}.announcement-rendered pre{background:var(--surface-2);padding:12px;border-radius:8px;white-space:pre-wrap}@media(max-width:700px){.announcement-editor-heading{top:58px}.announcement-editor .ql-toolbar.ql-snow{top:140px}.announcement-toolbar-label{align-items:flex-start;flex-direction:column}}
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const host=document.getElementById('announcement-editor'),field=document.getElementById('announcement-content');if(!host||!field||!window.Quill||host.dataset.ready)return;host.dataset.ready='1';
 const toolbar=[[{header:[1,2,3,4,5,6,false]}],['bold','italic','underline','strike'],[{list:'ordered'},{list:'bullet'}],[{indent:'-1'},{indent:'+1'}],[{align:[]}],['blockquote','link','image'],['clean']];
 const quill=new Quill(host,{theme:'snow',placeholder:'Tulis pengumuman dengan jelas dan terstruktur...',modules:{toolbar:{container:toolbar,handlers:{image:function(){imageInput.click();},link:function(value){if(!value){this.quill.format('link',false);return;}const url=prompt('Masukkan alamat tautan (https:// atau http://):');if(url&&/^https?:\/\//i.test(url)){this.quill.format('link',url);}else if(url){alert('Tautan harus menggunakan http:// atau https://');}}}}}});
 if(field.value)quill.clipboard.dangerouslyPasteHTML(field.value);
 const imageInput=document.createElement('input');imageInput.type='file';imageInput.accept='image/jpeg,image/png,image/webp';imageInput.hidden=true;document.body.appendChild(imageInput);
 imageInput.addEventListener('change',async()=>{const file=imageInput.files[0];if(!file)return;if(file.size>2*1024*1024){alert('Ukuran gambar maksimal 2 MB.');imageInput.value='';return;}const body=new FormData();body.append('image',file);try{const response=await fetch(@json(route('announcements.images.upload')),{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body});if(!response.ok)throw new Error('Upload gagal.');const result=await response.json();const range=quill.getSelection(true);quill.insertEmbed(range.index,'image',result.url,'user');quill.setSelection(range.index+1);}catch(e){alert('Gagal mengunggah gambar. Periksa koneksi dan hak akses.');}finally{imageInput.value='';}});
 const stats=document.getElementById('announcement-word-stats'),preview=document.getElementById('announcement-preview'),previewContent=document.getElementById('announcement-preview-content'),toggle=document.getElementById('announcement-preview-toggle');
 const updateStats=()=>{const text=quill.getText().trim();const words=text?text.split(/\s+/u).length:0;const chars=text.length;const mins=Math.max(1,Math.ceil(words/200));stats.textContent=`${words.toLocaleString('id-ID')} kata · ${chars.toLocaleString('id-ID')} karakter · ${mins} menit baca`;};
 const syncPreview=()=>{previewContent.innerHTML=quill.root.innerHTML;};quill.on('text-change',()=>{updateStats();if(!preview.hidden)syncPreview();});
 toggle.addEventListener('click',()=>{preview.hidden=!preview.hidden;if(!preview.hidden)syncPreview();toggle.innerHTML=preview.hidden?'<i class="fa-regular fa-eye"></i> Tampilkan preview':'<i class="fa-regular fa-eye-slash"></i> Sembunyikan preview';});
 host.closest('form')?.addEventListener('submit',e=>{field.value=quill.root.innerHTML;if(!quill.getText().trim()){e.preventDefault();alert('Isi pengumuman wajib diisi.');}});updateStats();
});
</script>
@endpush
@endonce
