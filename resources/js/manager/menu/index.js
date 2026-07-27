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

function rupiah(n) {
    return "Rp " + Number(n || 0).toLocaleString("id-ID");
}

const errorEl = document.getElementById("menuError");

function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = "block";
}

let allRows = [];
let kategoriList = [];

function isKasir() {
    try {
        const rawUser = localStorage.getItem("auth_user");
        return rawUser ? JSON.parse(rawUser).role === "kasir" : false;
    } catch (e) {
        return false;
    }
}

async function loadKategori() {
    try {
        const res = await fetch(`${API_BASE}/kategori`, fetchOptions());

        const json = await res.json();

        kategoriList = json.data || [];

        const optionsList = document.getElementById("filterKategoriOptions");

        kategoriList.forEach((k) => {
            const li = document.createElement("li");
            li.dataset.value = k.id;
            li.textContent = k.nama;
            optionsList.appendChild(li);
        });

        initCustomSelectKategori();
    } catch (e) {}
}

function initCustomSelectKategori() {
    const wrap    = document.getElementById("filterKategoriWrap");
    const trigger = wrap.querySelector(".custom-select-trigger");
    const label   = wrap.querySelector(".selected-label");
    const hidden  = document.getElementById("filterKategori");

    trigger.addEventListener("click", (e) => {
        e.stopPropagation();
        document.querySelectorAll(".custom-select.open").forEach((open) => {
            if (open !== wrap) open.classList.remove("open");
        });
        wrap.classList.toggle("open");
    });

    // event delegation supaya opsi yang ditambah belakangan tetap kepegang
    wrap.querySelector(".custom-options").addEventListener("click", (e) => {
        const option = e.target.closest("li");
        if (!option) return;

        wrap.querySelectorAll(".custom-options li").forEach((o) =>
            o.classList.remove("selected")
        );
        option.classList.add("selected");

        label.textContent = option.textContent;
        hidden.value = option.dataset.value;

        wrap.classList.remove("open");

        render();
    });

    document.addEventListener("click", () => {
        wrap.classList.remove("open");
    });
}

async function loadMenu() {
    const tbody = document.getElementById("menuTableBody");

    tbody.innerHTML =
        '<tr><td colspan="7" class="menu-loading">Memuat data...</td></tr>';

    try {
        const res = await fetch(`${API_BASE}/menu-internal`, fetchOptions());

        if (res.status === 401)
            throw new Error("Sesi habis, silakan login ulang.");

        if (res.status === 403)
            throw new Error("Akun tidak memiliki akses.");

        if (!res.ok)
            throw new Error("Gagal memuat data.");

        const json = await res.json();

        allRows = json.data || [];

        render();
    } catch (err) {
        tbody.innerHTML =
            '<tr><td colspan="7" class="menu-empty">Gagal memuat data.</td></tr>';

        showError(err.message);
    }
}

function groupRows(rows) {
    const groups = {};

    rows.forEach((r) => {

        const key = (r.kategori_id ?? "none") + "::" + r.nama;

        if (!groups[key]) {

            groups[key] = {
                kategori_id: r.kategori_id ?? null,
                kategori_nama: r.kategori ?? "-",
                nama: r.nama,
                rasa: r.rasa,
                variants: [],
            };

        }

        groups[key].variants.push({
            id: r.id,
            ukuran: r.ukuran,
            harga: r.harga,
            is_active: r.is_active,
        });
    });

    return Object.values(groups);
}

function render() {

    const keyword = document
        .getElementById("filterSearch")
        .value.trim()
        .toLowerCase();

    const kategoriFilter =
        document.getElementById("filterKategori").value;

    let filtered = allRows;

    if (kategoriFilter) {

        filtered = filtered.filter(
            (r) => String(r.kategori_id) === kategoriFilter
        );

    }

    if (keyword) {

        filtered = filtered.filter((r) =>
            r.nama.toLowerCase().includes(keyword)
        );

    }

    const groups = groupRows(filtered);

    const tbody = document.getElementById("menuTableBody");
    const kasir = isKasir();
    const colCount = kasir ? 6 : 7;

    if (groups.length === 0) {

        tbody.innerHTML =
            `<tr><td colspan="${colCount}" class="menu-empty">Tidak ada menu.</td></tr>`;

        return;
    }

    tbody.innerHTML = groups
        .map((g) => {

            const hargaList = g.variants.map((v) => v.harga);

            const min = Math.min(...hargaList);
            const max = Math.max(...hargaList);

            const hargaText =
                min === max
                    ? rupiah(min)
                    : `${rupiah(min)} - ${rupiah(max)}`;

            const ukuranText =
                g.variants.length + " Ukuran";

            const tersedia = g.variants.some((v) => v.is_active);

            // id representatif untuk grup ini (dipakai halaman edit
            // untuk meng-group ulang semua varian dengan kategori_id + nama yang sama)
            const representativeId = g.variants[0].id;

            const editUrl = `/menu/${representativeId}/edit`;

            return `
<tr>

<td class="menu-nama">${g.nama}</td>

<td>${g.kategori_nama}</td>

<td>${hargaText}</td>

<td>${ukuranText}</td>

<td>${g.rasa ?? "-"}</td>

<td>
<span class="status-badge ${
                tersedia
                    ? "status-tersedia"
                    : "status-habis"
            }">
${tersedia ? "Tersedia" : "Habis"}
</span>
</td>

${kasir ? "" : `
<td>

<div class="action-btns">

<a href="${editUrl}" class="btn-edit">
<i class="fa-regular fa-pen-to-square"></i>
</a>

<button
class="btn-delete"
data-kategori="${g.kategori_id}"
data-nama="${g.nama}">
<i class="fa-regular fa-trash-can"></i>
</button>

</div>

</td>
`}

</tr>`;
        })
        .join("");

    if (!kasir) {
        document.querySelectorAll(".btn-delete").forEach((btn) => {

            btn.onclick = () =>
                openDeleteModal(
                    btn.dataset.kategori,
                    btn.dataset.nama
                );

        });
    }
}

let pendingDelete = null;

const deleteOverlay =
    document.getElementById("deleteOverlay");

function openDeleteModal(kategoriId, nama) {

    pendingDelete = {
        kategoriId,
        nama,
    };

    document.getElementById(
        "deleteMessage"
    ).textContent =
        `Apakah anda yakin ingin menghapus '${nama}'?`;

    deleteOverlay.classList.add("open");
}

document
    .getElementById("deleteCancelBtn")
    .addEventListener("click", () => {

        deleteOverlay.classList.remove("open");

        pendingDelete = null;
    });

document
    .getElementById("deleteConfirmBtn")
    .addEventListener("click", async () => {

        if (!pendingDelete) return;

        const rows = allRows.filter(
            (r) =>
                String(r.kategori_id) ===
                    String(pendingDelete.kategoriId) &&
                r.nama === pendingDelete.nama
        );

        try {

            await Promise.all(
                rows.map((r) =>
                    fetch(
                        `${API_BASE}/menu/${r.id}`,
                        fetchOptions({
                            method: "DELETE",
                        })
                    )
                )
            );

            deleteOverlay.classList.remove("open");

            pendingDelete = null;

            loadMenu();

        } catch (e) {

            showError(
                "Gagal menghapus menu."
            );

        }
    });

document
    .getElementById("filterSearch")
    .addEventListener(
        "input",
        debounce(render, 250)
    );

function debounce(fn, delay) {

    let timer;

    return (...args) => {

        clearTimeout(timer);

        timer = setTimeout(
            () => fn(...args),
            delay
        );
    };
}

document.addEventListener("DOMContentLoaded", () => {

    loadKategori();

    loadMenu();

});