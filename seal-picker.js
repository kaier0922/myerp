/**
 * =====================================================
 * 文件名：seal-picker.js（修正版）
 * 功能：电子公章可视化选择与拖拽定位
 * 作者：ChatGPT 修正版（防消失稳定版）
 * =====================================================
 */

class SealPicker {
    constructor(options = {}) {
        this.container = options.container || document.body;
        this.onSave = options.onSave || null;
        this.seals = [];
        this.currentSeal = null;
        this.sealElement = null;
        this.isDragging = false;
        this.offset = { x: 0, y: 0 };
        this.init();
    }

    async init() {
        await this.loadSeals();
        this.createUI();
    }

    async loadSeals() {
        try {
            const res = await fetch("ajax_seals.php?action=get_seals");
            const data = await res.json();
            if (data.success) this.seals = data.seals;
        } catch (err) {
            console.error("❌ 加载公章失败:", err);
        }
    }

    createUI() {
        const btn = document.createElement("button");
        btn.className = "seal-picker-btn";
        btn.textContent = "🖊️ 盖章";
        btn.onclick = () => this.showModal();
        this.container?.appendChild(btn);
    }

    showModal() {
        const modal = document.createElement("div");
        modal.className = "seal-modal";
        modal.innerHTML = `
            <div class="seal-modal-content">
                <div class="seal-modal-header">
                    <h3>选择公章</h3>
                    <button class="seal-modal-close" title="关闭">×</button>
                </div>
                <div class="seal-modal-body">
                    ${
                        this.seals.length
                            ? this.renderSealGrid()
                            : '<p class="seal-empty">暂无公章，请先上传</p>'
                    }
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector(".seal-modal-close").onclick = () => modal.remove();
        modal.addEventListener("click", (e) => {
            if (e.target === modal) modal.remove();
        });
    }

    renderSealGrid() {
        return `
            <div class="seal-grid">
                ${this.seals
                    .map(
                        (s) => `
                    <div class="seal-item" onclick="sealPicker.selectSeal(${s.id})">
                        <img src="uploads/seals/${s.file_path}" alt="${s.seal_name}">
                        <div class="seal-item-name">${s.seal_name}</div>
                        <div class="seal-item-type">${s.seal_type}</div>
                    </div>`
                    )
                    .join("")}
            </div>
        `;
    }

    selectSeal(id) {
        const seal = this.seals.find((s) => s.id === id);
        if (!seal) return;
        this.currentSeal = seal;
        document.querySelector(".seal-modal")?.remove();
        this.addSealToDocument();
        this.showTip("✅ 已添加公章，可拖动调整位置");
    }

    addSealToDocument() {
        if (this.sealElement) this.sealElement.remove();

        const sealDiv = document.createElement("div");
        sealDiv.className = "document-seal draggable";
        sealDiv.innerHTML = `
            <img src="uploads/seals/${this.currentSeal.file_path}" alt="${this.currentSeal.seal_name}">
            <div class="seal-controls">
                <button class="seal-btn" onclick="sealPicker.adjustSize(-10)">-</button>
                <button class="seal-btn" onclick="sealPicker.adjustSize(10)">+</button>
                <button class="seal-btn" onclick="sealPicker.removeSeal()">×</button>
            </div>
        `;

        // 默认显示位置
        sealDiv.style.position = "absolute";
        sealDiv.style.left = "60%";
        sealDiv.style.top = "60%";
        sealDiv.style.width = "120px";
        sealDiv.style.height = "120px";
        sealDiv.style.zIndex = "999";
        sealDiv.style.cursor = "grab";
        sealDiv.style.opacity = "1";
        sealDiv.style.transition = "opacity 0.3s ease";

        // 确保父容器有相对定位
        const target =
            document.querySelector(".print-area") ||
            document.querySelector(".document-container") ||
            document.body;
        if (target && getComputedStyle(target).position === "static") {
            target.style.position = "relative";
            target.style.overflow = "visible";
        }

        target.appendChild(sealDiv);
        this.sealElement = sealDiv;
        this.enableDragging();
    }

    enableDragging() {
        const seal = this.sealElement;
        const img = seal.querySelector("img");

        img.addEventListener("mousedown", (e) => {
            this.isDragging = true;
            const rect = seal.getBoundingClientRect();
            this.offset.x = e.clientX - rect.left;
            this.offset.y = e.clientY - rect.top;
            seal.style.cursor = "grabbing";
            seal.classList.add("dragging");
            e.preventDefault();
        });

        document.addEventListener("mousemove", (e) => {
            if (!this.isDragging) return;
            const container = seal.parentElement;
            const cRect = container.getBoundingClientRect();
            let x = e.clientX - cRect.left - this.offset.x;
            let y = e.clientY - cRect.top - this.offset.y;

            // 限制在容器内部
            x = Math.max(0, Math.min(x, cRect.width - seal.offsetWidth));
            y = Math.max(0, Math.min(y, cRect.height - seal.offsetHeight));

            seal.style.left = `${x}px`;
            seal.style.top = `${y}px`;
        });

        document.addEventListener("mouseup", () => {
            if (this.isDragging) {
                this.isDragging = false;
                seal.style.cursor = "grab";
                seal.classList.remove("dragging");
                // 拖动结束后强制确保在可视区域
                this.ensureVisible(seal);
            }
        });
    }

    ensureVisible(seal) {
        const rect = seal.getBoundingClientRect();
        const container = seal.parentElement.getBoundingClientRect();
        const visible =
            rect.right > container.left &&
            rect.bottom > container.top &&
            rect.left < container.right &&
            rect.top < container.bottom;

        if (!visible) {
            console.warn("⚠️ 公章拖拽后位置异常，已自动修正");
            seal.style.left = "50%";
            seal.style.top = "60%";
            seal.style.display = "block";
            seal.style.opacity = "1";
        }
    }

    adjustSize(delta) {
        if (!this.sealElement) return;
        const w = this.sealElement.offsetWidth;
        const newW = Math.max(60, Math.min(300, w + delta));
        this.sealElement.style.width = `${newW}px`;
        this.sealElement.style.height = `${newW}px`;
    }

    removeSeal() {
        if (this.sealElement) {
            this.sealElement.remove();
            this.sealElement = null;
            this.currentSeal = null;
        }
    }

    getSealPosition() {
        if (!this.sealElement || !this.currentSeal) return null;
        const rect = this.sealElement.getBoundingClientRect();
        const container = this.sealElement.parentElement.getBoundingClientRect();
        return {
            seal_id: this.currentSeal.id,
            x: rect.left - container.left,
            y: rect.top - container.top,
            size: this.sealElement.offsetWidth,
        };
    }

    showTip(msg) {
        const tip = document.createElement("div");
        tip.className = "seal-tip";
        tip.textContent = msg;
        Object.assign(tip.style, {
            position: "fixed",
            bottom: "20px",
            left: "50%",
            transform: "translateX(-50%)",
            background: "#222",
            color: "#fff",
            padding: "6px 12px",
            borderRadius: "8px",
            fontSize: "13px",
            opacity: "0",
            transition: "opacity 0.3s ease",
            zIndex: "2000",
        });
        document.body.appendChild(tip);
        setTimeout(() => (tip.style.opacity = "0.9"), 10);
        setTimeout(() => {
            tip.style.opacity = "0";
            setTimeout(() => tip.remove(), 300);
        }, 2500);
    }
}

// 全局实例
let sealPicker;
document.addEventListener("DOMContentLoaded", () => {
    const container = document.querySelector(".seal-picker-container");
    if (container) sealPicker = new SealPicker({ container });
});

// 🧩 修复方案：防止报价单页面重新渲染导致公章被删除
const observer = new MutationObserver((mutations) => {
    for (const m of mutations) {
        if (m.type === 'childList') {
            if (window.sealPicker && window.sealPicker.sealElement) {
                const exists = document.body.contains(window.sealPicker.sealElement);
                if (!exists) {
                    console.warn('⚠️ 公章节点被意外移除，正在恢复...');
                    const container = document.querySelector('.print-area') || document.body;
                    container.appendChild(window.sealPicker.sealElement);
                    window.sealPicker.sealElement.style.display = 'block';
                    window.sealPicker.sealElement.style.opacity = '1';
                }
            }
        }
    }
});

// 监听整个报价单区域
document.addEventListener('DOMContentLoaded', () => {
    const target = document.querySelector('.print-area');
    if (target) observer.observe(target, { childList: true, subtree: true });
});
