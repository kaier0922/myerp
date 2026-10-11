/**
 * =====================================================
 * 文件名：seal-picker.js（A4比例修正版）
 * 功能：可拖拽、等比例真实显示的电子公章
 * =====================================================
 */
class SealPicker {
    constructor(options = {}) {
        this.container = options.container || document.body;
        this.seals = [];
        this.currentSeal = null;
        this.sealElement = null;
        this.isDragging = false;
        this.offset = { x: 0, y: 0 };
        this.A4_SCALE = 3.78; // 1mm = 3.78px
        this.init();
    }

    async init() {
        await this.loadSeals();
        this.createButton();
        this.observePrintArea();
    }

    async loadSeals() {
        try {
            const r = await fetch("ajax_seals.php?action=get_seals");
            const d = await r.json();
            if (d.success) this.seals = d.seals;
        } catch (e) {
            console.error("加载公章失败", e);
        }
    }

    createButton() {
        const btn = document.createElement("button");
        btn.className = "seal-picker-btn";
        btn.textContent = "🖊️ 盖章";
        btn.onclick = () => this.showModal();
        this.container.appendChild(btn);
    }

    showModal() {
        const modal = document.createElement("div");
        modal.className = "seal-modal";
        modal.innerHTML = `
            <div class="seal-modal-content">
                <h3>选择公章</h3>
                <div class="seal-grid">
                    ${this.seals
                        .map(
                            s => `
                        <div class="seal-item" onclick="sealPicker.selectSeal(${s.id})">
                            <img src="uploads/seals/${s.file_path}">
                            <div>${s.seal_name}</div>
                        </div>`
                        )
                        .join("")}
                </div>
                <button class="seal-close">关闭</button>
            </div>`;
        document.body.appendChild(modal);
        modal.querySelector(".seal-close").onclick = () => modal.remove();
        modal.addEventListener("click", e => {
            if (e.target === modal) modal.remove();
        });
    }

    selectSeal(id) {
        const s = this.seals.find(x => x.id === id);
        if (!s) return;
        this.currentSeal = s;
        document.querySelector(".seal-modal")?.remove();
        this.addSealToDocument();
    }

    addSealToDocument() {
        if (this.sealElement) this.sealElement.remove();

        const div = document.createElement("div");
        div.className = "document-seal";
        div.innerHTML = `<img src="uploads/seals/${this.currentSeal.file_path}">`;

        // 公章直径设为 38mm（真实章约 3.8cm）
        const sizePx = 38 * this.A4_SCALE;
        div.style.width = `${sizePx}px`;
        div.style.height = `${sizePx}px`;
        div.style.position = "absolute";
        div.style.left = "130mm"; // 右下角偏右
        div.style.top = "230mm";
        div.style.zIndex = 999;
        div.style.cursor = "grab";
        div.style.transition = "opacity 0.3s";

        const target = document.querySelector(".print-area");
        if (getComputedStyle(target).position === "static") {
            target.style.position = "relative";
        }
        target.appendChild(div);

        this.sealElement = div;
        this.enableDragging();
    }

    enableDragging() {
        const seal = this.sealElement;
        const img = seal.querySelector("img");

        img.addEventListener("mousedown", e => {
            this.isDragging = true;
            const rect = seal.getBoundingClientRect();
            this.offset.x = e.clientX - rect.left;
            this.offset.y = e.clientY - rect.top;
            seal.style.cursor = "grabbing";
        });

        document.addEventListener("mousemove", e => {
            if (!this.isDragging) return;
            const container = seal.parentElement.getBoundingClientRect();
            let x = e.clientX - container.left - this.offset.x;
            let y = e.clientY - container.top - this.offset.y;
            x = Math.max(0, Math.min(x, container.width - seal.offsetWidth));
            y = Math.max(0, Math.min(y, container.height - seal.offsetHeight));
            seal.style.left = `${x}px`;
            seal.style.top = `${y}px`;
        });

        document.addEventListener("mouseup", () => {
            if (this.isDragging) {
                this.isDragging = false;
                seal.style.cursor = "grab";
                this.ensureVisible();
            }
        });
    }

    ensureVisible() {
        if (!this.sealElement) return;
        const s = this.sealElement;
        const rect = s.getBoundingClientRect();
        const parent = s.parentElement.getBoundingClientRect();
        if (rect.bottom < parent.top || rect.top > parent.bottom) {
            s.style.left = "100mm";
            s.style.top = "220mm";
        }
    }

    observePrintArea() {
        const area = document.querySelector(".print-area");
        if (!area) return;
        const obs = new MutationObserver(() => {
            if (this.sealElement && !document.body.contains(this.sealElement)) {
                console.warn("⚠️ 公章节点被移除，重新挂载");
                area.appendChild(this.sealElement);
            }
        });
        obs.observe(area, { childList: true, subtree: true });
    }
}

let sealPicker;
document.addEventListener("DOMContentLoaded", () => {
    const c = document.querySelector(".seal-picker-container");
    if (c) sealPicker = new SealPicker({ container: c });
});
