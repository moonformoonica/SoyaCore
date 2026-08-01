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

const errorEl = document.getElementById("createError");

function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = "block";
    errorEl.scrollIntoView({ behavior: "smooth", block: "center" });
}

function clearError() {
    errorEl.style.display = "none";
    errorEl.textContent = "";
}

/* ===========================
        KATEGORI
=========================== */

async function loadKategori() {
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
    } catch (e) {
        showError("Gagal memuat daftar kategori.");
    }
}

/* ===========================
        RASA (TAGS)
=========================== */

let rasaTags = [];

const rasaTagList   = document.getElementById("rasaTagList");
const addRasaBtn     = document.getElementById("addRasaBtn");
const rasaInputRow   = document.getElementById("rasaInputRow");
const rasaInput      = document.getElementById("rasaInput");
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

const UKURAN_PRESET = ["Hot", "Reguler", "Large", "250ml", "500ml", "1000ml"];
let golonganUkuran = {};

const ukuranGrid = document.getElementById("ukuranGrid");

async function loadGolonganUkuran() {
    try {
        const res = await fetch(`${API_BASE}/menu-internal`, fetchOptions());
        if (!res.ok) return;
        ((await res.json()).data || []).forEach((r) => {
            if (r.golongan_ukuran) golonganUkuran[r.ukuran ?? ""] = r.golongan_ukuran;
        });
    } catch (e) {
    }
}

function renderUkuranGrid() {
    const baris = (ukuran) => `
        <div class="ukuran-row">
            <span class="ukuran-pill">${ukuran}</span>
            <input
                type="number"
                min="0"
                step="500"
                placeholder="Rp0"
                data-ukuran="${ukuran}"
                class="ukuran-harga-input">
        </div>
    `;

    const kolom = { cup: [], botol: [], lainnya: [] };
    UKURAN_PRESET.forEach((u) => {
        const golongan = golonganUkuran[u] || "lainnya";
        (kolom[golongan] || kolom.lainnya).push(baris(u));
    });

    const bagian = (judul, isi) =>
        isi.length ? `<span class="ukuran-kolom-judul">${judul}</span>${isi.join("")}` : "";
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
        SUBMIT
=========================== */

const form    = document.getElementById("createMenuForm");
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
            ukuranData.map((item) =>
                fetch(`${API_BASE}/menu`, fetchOptions({
                    method: "POST",
                    body: JSON.stringify({
                        kategori_id: kategoriId,
                        nama: nama,
                        rasa: rasaText,
                        ukuran: item.ukuran,
                        harga: item.harga,
                        is_active: isActive,
                    }),
                }))
            )
        );

        window.location.href = "/menu";

    } catch (err) {
        showError("Gagal menyimpan menu. Silakan coba lagi.");
        btnSave.disabled = false;
        btnSave.textContent = "Simpan";
    }
});

/* ===========================
        INIT
=========================== */

document.addEventListener("DOMContentLoaded", async () => {
    loadKategori();
    renderRasaTags();
    renderUkuranGrid();
    await loadGolonganUkuran();
    renderUkuranGrid();
});