const API_BASE = "/api";

function getToken() {
    return localStorage.getItem("auth_token");
}

function fetchOptions(extra = {}) {
    const token = getToken();

    return {
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            ...(token ? { Authorization: "Bearer " + token } : {}),
        },
        ...extra,
    };
}

const errorEl = document.getElementById("editError");

function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = "block";
    errorEl.scrollIntoView({ behavior: "smooth", block: "center" });
}

function clearError() {
    errorEl.style.display = "none";
    errorEl.textContent = "";
}

const menuId = document.getElementById("editMenuForm").dataset.menuId;

/* ===========================
        KATEGORI
=========================== */

async function loadKategori(selectedId = null) {
    try {
        const res = await fetch(`${API_BASE}/kategori`, fetchOptions());
        const json = await res.json();
        const list = json.data || [];

        const select = document.getElementById("kategoriSelect");

        list.forEach((k) => {
            const opt = document.createElement("option");
            opt.value = k.id;
            opt.textContent = k.nama;
            select.appendChild(opt);
        });

        if (selectedId) {
            select.value = selectedId;
        }
    } catch (e) {
        showError("Gagal memuat daftar kategori.");
    }
}

/* ===========================
        RASA (TAGS)
=========================== */

let rasaTags = [];

const rasaTagList    = document.getElementById("rasaTagList");
const addRasaBtn      = document.getElementById("addRasaBtn");
const rasaInputRow    = document.getElementById("rasaInputRow");
const rasaInput       = document.getElementById("rasaInput");
const rasaInputCancel = document.getElementById("rasaInputCancel");

function renderRasaTags() {
    rasaTagList.querySelectorAll(".tag-pill").forEach((el) => el.remove());

    rasaTags.forEach((tag, idx) => {
        const pill = document.createElement("span");
        pill.className = "tag-pill";
        pill.innerHTML = `${tag} <button type="button" data-idx="${idx}"><i class="fa-solid fa-xmark"></i></button>`;
        rasaTagList.insertBefore(pill, addRasaBtn);
    });

    rasaTagList.querySelectorAll(".tag-pill button").forEach((btn) => {
        btn.addEventListener("click", () => {
            rasaTags.splice(Number(btn.dataset.idx), 1);
            renderRasaTags();
        });
    });
}

addRasaBtn.addEventListener("click", () => {
    rasaInputRow.style.display = "flex";
    rasaInput.value = "";
    rasaInput.focus();
});

rasaInputCancel.addEventListener("click", () => {
    rasaInputRow.style.display = "none";
});

rasaInput.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        e.preventDefault();
        const val = rasaInput.value.trim();

        if (val) {
            rasaTags.push(val);
            renderRasaTags();
        }

        rasaInput.value = "";
        rasaInputRow.style.display = "none";
    }
});

/* ===========================
        UKURAN + HARGA
=========================== */

// Urutannya sengaja sama dengan urutan dari backend (Hot → Reguler → Large →
// 250ml → 500ml → 1000ml); jangan di-sort ulang.
const UKURAN_PRESET = ["Hot", "Reguler", "Large", "250ml", "500ml", "1000ml"];

// Golongan tiap ukuran diambil dari `golongan_ukuran` milik backend, bukan
// ditebak dari string, supaya penambahan ukuran baru cukup diurus di satu
// tempat. Diisi saat data menu dimuat.
let golonganUkuran = {};

const ukuranGrid = document.getElementById("ukuranGrid");

function barisUkuran(ukuran, hargaMap) {
    const existing = hargaMap[ukuran];
    const harga = existing ? existing.harga : "";
    const varianId = existing ? existing.id : "";
    // Dessert & cookies tersimpan dengan ukuran string kosong.
    const label = ukuran === "" ? "Tanpa ukuran" : ukuran;

    return `
        <div class="ukuran-row">
            <span class="ukuran-pill">${label}</span>
            <input
                type="number"
                min="0"
                step="500"
                placeholder="Rp0"
                data-ukuran="${ukuran}"
                data-varian-id="${varianId}"
                value="${harga}"
                class="ukuran-harga-input">
        </div>
    `;
}

function renderUkuranGrid(existingVarian = []) {
    // existingVarian: [{ id, ukuran, harga }]
    const hargaMap = {};
    existingVarian.forEach((v) => {
        hargaMap[v.ukuran] = v;
    });

    // Preset dulu, lalu ukuran milik menu ini yang di luar preset (mis. dessert
    // dengan ukuran kosong) supaya tidak hilang dari layar, kalau hilang,
    // harganya ikut terhapus saat disimpan.
    const daftar = UKURAN_PRESET.slice();
    existingVarian.forEach((v) => {
        if (!daftar.includes(v.ukuran)) daftar.push(v.ukuran);
    });

    const kolom = { cup: [], botol: [], lainnya: [] };
    daftar.forEach((u) => {
        const golongan = golonganUkuran[u] || "lainnya";
        (kolom[golongan] || kolom.lainnya).push(barisUkuran(u, hargaMap));
    });

    const bagian = (judul, isi) =>
        isi.length ? `<span class="ukuran-kolom-judul">${judul}</span>${isi.join("")}` : "";

    // Cup di kiri, botol di kanan. "Lainnya" menempel di kolom kiri supaya
    // tetap terlihat, bukan dibuang.
    ukuranGrid.innerHTML = `
        <div class="ukuran-kolom">
            ${bagian("Cup", kolom.cup)}
            ${bagian("Lainnya", kolom.lainnya)}
        </div>
        <div class="ukuran-kolom">
            ${bagian("Botol", kolom.botol)}
        </div>
    `;
}

function getUkuranData() {
    const inputs = ukuranGrid.querySelectorAll(".ukuran-harga-input");

    const result = [];

    inputs.forEach((input) => {
        const harga = input.value.trim();

        if (harga) {
            result.push({
                id: input.dataset.varianId || null,
                ukuran: input.dataset.ukuran,
                harga: Number(harga),
            });
        }
    });

    return result;
}

/* ===========================
        STATUS TOGGLE
=========================== */

const statusToggle      = document.getElementById("statusToggle");
const statusToggleLabel = document.getElementById("statusToggleLabel");

statusToggle.addEventListener("change", () => {
    statusToggleLabel.textContent = statusToggle.checked ? "Tersedia" : "Habis";
});

/* ===========================
        LOAD DATA MENU
=========================== */

// GET /api/menu-internal (flat, satu row = satu ukuran, termasuk nonaktif).
// Jadi di sini kita ambil semua row, cari row yang id-nya cocok
// dengan menuId dari URL, lalu group ulang row lain yang
// kategori_id + nama-nya sama, sama seperti logic groupRows() di index.js.
async function loadMenuData() {
    try {
        const res = await fetch(`${API_BASE}/menu-internal`, fetchOptions());

        if (!res.ok) {
            throw new Error("Gagal memuat data menu.");
        }

        const json = await res.json();
        const allRows = json.data || [];

        // Peta ukuran -> golongan dari backend, dipakai memisah kolom cup/botol.
        allRows.forEach((r) => {
            if (r.golongan_ukuran) golonganUkuran[r.ukuran ?? ""] = r.golongan_ukuran;
        });

        const anchorRow = allRows.find((r) => String(r.id) === String(menuId));

        if (!anchorRow) {
            throw new Error("Menu tidak ditemukan.");
        }

        const groupRows = allRows.filter(
            (r) =>
                String(r.kategori_id) === String(anchorRow.kategori_id) &&
                r.nama === anchorRow.nama
        );

        document.getElementById("namaProduk").value = anchorRow.nama || "";

        rasaTags = (anchorRow.rasa || "")
            .split("+")
            .map((r) => r.trim())
            .filter(Boolean);
        renderRasaTags();

        const varian = groupRows.map((r) => ({
            id: r.id,
            ukuran: r.ukuran,
            harga: r.harga,
        }));
        renderUkuranGrid(varian);

        const isActive = groupRows.some((r) => r.is_active);
        statusToggle.checked = isActive;
        statusToggleLabel.textContent = isActive ? "Tersedia" : "Habis";

        await loadKategori(anchorRow.kategori_id);

    } catch (e) {
        showError("Gagal memuat data menu. Silakan coba lagi.");
        await loadKategori();
        renderUkuranGrid([]);
    }
}

/* ===========================
        SUBMIT
=========================== */

const form    = document.getElementById("editMenuForm");
const btnSave = document.getElementById("btnSave");

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearError();

    const nama       = document.getElementById("namaProduk").value.trim();
    const kategoriId = document.getElementById("kategoriSelect").value;
    const isActive   = statusToggle.checked;
    const ukuranData = getUkuranData();

    if (!nama) {
        return showError("Nama Produk wajib diisi.");
    }

    if (!kategoriId) {
        return showError("Kategori wajib dipilih.");
    }

    if (rasaTags.length === 0) {
        return showError("Minimal tambahkan 1 rasa.");
    }

    if (ukuranData.length === 0) {
        return showError("Isi harga minimal 1 ukuran.");
    }

    const rasaText = rasaTags.join(" + ");

    btnSave.disabled = true;
    btnSave.textContent = "Menyimpan...";

    try {
        await Promise.all(
            ukuranData.map((item) => {
                const isExisting = !!item.id;

                return fetch(
                    isExisting ? `${API_BASE}/menu/${item.id}` : `${API_BASE}/menu`,
                    fetchOptions({
                        method: isExisting ? "PUT" : "POST",
                        body: JSON.stringify({
                            kategori_id: kategoriId,
                            nama: nama,
                            rasa: rasaText,
                            ukuran: item.ukuran,
                            harga: item.harga,
                            is_active: isActive,
                        }),
                    })
                );
            })
        );

        window.location.href = "/menu";

    } catch (err) {
        showError("Gagal menyimpan perubahan. Silakan coba lagi.");
        btnSave.disabled = false;
        btnSave.textContent = "Simpan";
    }
});

/* ===========================
        INIT
=========================== */

document.addEventListener("DOMContentLoaded", () => {
    renderRasaTags();
    loadMenuData();
});