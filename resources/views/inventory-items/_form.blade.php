
<div class="form-grid">
    <div class="field">
        <label>Kode barang</label>
        <input
            type="text"
            name="kode_item"
            value="{{ old('kode_item', $item->kode_item ?? '') }}"
            required
        >
    </div>

    <div class="field">
        <label>Nama barang</label>
        <input
            type="text"
            name="nama"
            value="{{ old('nama', $item->nama ?? '') }}"
            required
        >
    </div>

    <div class="field">
        <label>Kategori</label>
        <select name="kategori_id" required>
            <option value="">Pilih kategori</option>

            @foreach ($categories as $category)
                <option
                    value="{{ $category->id }}"
                    @selected(old('kategori_id', $item->kategori_id ?? '') == $category->id)
                >
                    {{ $category->nama }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Status</label>
        <select name="status">
            <option
                value="ACTIVE"
                @selected(old('status', $item->status ?? 'ACTIVE') === 'ACTIVE')
            >
                Aktif
            </option>

            <option
                value="INACTIVE"
                @selected(old('status', $item->status ?? '') === 'INACTIVE')
            >
                Tidak aktif
            </option>
        </select>
    </div>

    <div class="field full">
        <label>Deskripsi</label>
        <textarea name="deskripsi">{{ old('deskripsi', $item->deskripsi ?? '') }}</textarea>
    </div>
</div>
