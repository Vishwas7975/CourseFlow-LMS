<?php
/*
 * admin/certificate_template.php — CourseFlow LMS
 * Drag-and-drop certificate template designer.
 * Admin-only. Saves template to DB via save_template.php.
 */
session_start();
include("../config/db.php");
include("../includes/auth_check.php");
requireLogin($conn);
requireRole("admin");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate Template Designer – CourseFlow LMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ── Reset for full-viewport editor ──────────────────────── */
html, body { height: 100%; overflow: hidden; }

/* ── Top bar (matches project topbar style) ──────────────── */
.designer-topbar {
  height: 60px;
  background: rgba(255,255,255,0.97);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky; top: 0; z-index: 200;
  box-shadow: var(--shadow-xs);
  gap: 12px;
}
.designer-topbar-left {
  display: flex; align-items: center; gap: 12px;
  flex-shrink: 0;
}
.designer-topbar-left h1 {
  font-size: 0.98rem; font-weight: 700;
  color: var(--text-primary);
  display: flex; align-items: center; gap: 8px;
}
.designer-topbar-left h1 i { color: var(--primary); }
.designer-topbar-right { display: flex; gap: 8px; flex-shrink: 0; }

/* ── 3-column editor layout ──────────────────────────────── */
.editor-wrap {
  display: grid;
  grid-template-columns: 200px 1fr 260px;
  height: calc(100vh - 60px);
  overflow: hidden;
}

/* ── LEFT: Field toolbox ─────────────────────────────────── */
.toolbox {
  background: var(--card);
  border-right: 1px solid var(--border);
  padding: 16px 12px;
  overflow-y: auto;
  display: flex; flex-direction: column; gap: 0;
}
.toolbox-heading {
  font-size: 0.68rem; font-weight: 700;
  letter-spacing: 1.1px; text-transform: uppercase;
  color: var(--text-muted);
  padding: 0 4px 10px;
  border-bottom: 1px solid var(--border-light);
  margin-bottom: 12px;
}
.field-chip {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 12px; margin-bottom: 6px;
  background: var(--bg); border: 1.5px solid var(--border-light);
  border-radius: var(--radius-sm);
  font-size: 0.8rem; font-weight: 600;
  color: var(--primary-dark);
  cursor: grab; user-select: none;
  transition: all var(--transition);
}
.field-chip i { font-size: 0.72rem; color: var(--primary); width: 14px; text-align: center; }
.field-chip:hover { background: var(--primary-light); border-color: var(--primary); }
.field-chip.dragging { opacity: 0.4; }

/* ── CENTRE: Canvas area ─────────────────────────────────── */
.canvas-area {
  display: flex; flex-direction: column;
  align-items: center; padding: 24px;
  background: var(--bg); overflow: auto;
}
.upload-zone {
  width: 100%; max-width: 860px;
  border: 2.5px dashed var(--border);
  border-radius: var(--radius-lg);
  padding: 48px 24px; text-align: center;
  background: var(--card); cursor: pointer;
  transition: all var(--transition); margin-bottom: 16px;
}
.upload-zone:hover { border-color: var(--primary); background: var(--primary-light); }
.upload-zone i { font-size: 2.4rem; color: var(--text-muted); margin-bottom: 12px; display: block; }
.upload-zone p  { color: var(--text-secondary); font-size: 0.87rem; }
.upload-zone strong { color: var(--primary-dark); }
.upload-zone small  { color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 6px; }

#certificateCanvas {
  position: relative;
  background: var(--card);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-lg);
  width: 860px; min-height: 600px;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  overflow: hidden;
  display: none;
}

/* ── Field elements on canvas ────────────────────────────── */
.field-element {
  position: absolute; padding: 3px 8px;
  background: rgba(255,255,255,0.88);
  border: 1.5px dashed var(--primary);
  border-radius: 4px;
  cursor: move; font-size: 16px;
  white-space: nowrap; user-select: none;
  transition: background 0.15s;
}
.field-element:hover { background: rgba(255,255,255,0.97); }
.field-element.selected {
  border: 2px solid var(--primary-dark);
  background: #fff;
  box-shadow: 0 0 0 3px var(--primary-glow);
}

/* ── RIGHT: Properties panel ─────────────────────────────── */
.props-panel {
  background: var(--card);
  border-left: 1px solid var(--border);
  padding: 16px; overflow-y: auto;
}
.props-heading {
  font-size: 0.68rem; font-weight: 700;
  letter-spacing: 1.1px; text-transform: uppercase;
  color: var(--text-muted);
  padding: 0 4px 10px;
  border-bottom: 1px solid var(--border-light);
  margin-bottom: 14px;
}
.no-sel-msg {
  font-size: 0.8rem; color: var(--text-muted);
  text-align: center; padding: 20px 8px;
  line-height: 1.6;
}
.no-sel-msg i { font-size: 1.6rem; color: var(--border); display: block; margin-bottom: 8px; }
.prop-group { margin-bottom: 14px; }
.prop-group label {
  display: block; font-size: 0.76rem; font-weight: 600;
  color: var(--text-secondary); margin-bottom: 5px;
}
.prop-group input,
.prop-group select {
  width: 100%; padding: 8px 10px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 0.82rem; font-family: 'DM Sans', sans-serif;
  color: var(--text-primary); background: var(--bg);
  outline: none; transition: border-color var(--transition);
}
.prop-group input:focus,
.prop-group select:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px var(--primary-glow); }
.prop-group input[type="color"] { height: 38px; padding: 2px 4px; cursor: pointer; }
.prop-group input[type="range"] { padding: 6px 0; accent-color: var(--primary); }
.prop-size-row { display: flex; align-items: center; gap: 8px; }
.prop-size-val { min-width: 34px; font-size: 0.78rem; color: var(--text-muted); text-align: right; }
.prop-divider { border: none; border-top: 1px solid var(--border-light); margin: 16px 0; }

/* ── Preview modal ───────────────────────────────────────── */
.vs-modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(15,37,64,0.55); z-index: 500;
  align-items: center; justify-content: center;
}
.vs-modal-overlay.open { display: flex; }
.vs-modal {
  background: var(--card); border-radius: var(--radius-xl);
  width: 95%; max-width: 980px; max-height: 92vh;
  overflow: auto; padding: 28px;
  box-shadow: 0 24px 64px rgba(0,0,0,0.22);
  animation: modalIn 0.22s ease;
}
@keyframes modalIn { from { opacity:0; transform:scale(0.97); } to { opacity:1; transform:scale(1); } }
.vs-modal-header {
  display: flex; justify-content: space-between;
  align-items: center; margin-bottom: 20px;
}
.vs-modal-header h2 {
  font-size: 1rem; font-weight: 700;
  color: var(--text-primary);
  display: flex; align-items: center; gap: 8px;
}
.vs-modal-header h2 i { color: var(--primary); }
.vs-modal-close {
  width: 34px; height: 34px; border-radius: var(--radius-md);
  background: var(--bg); border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--text-secondary);
  transition: all var(--transition); font-size: 0.9rem;
}
.vs-modal-close:hover { background: var(--danger-light); color: var(--danger); border-color: var(--danger); }
.search-row { display: flex; gap: 8px; margin-bottom: 20px; }
.search-row input {
  flex: 1; padding: 9px 14px;
  border: 1.5px solid var(--border); border-radius: var(--radius-md);
  font-size: 0.87rem; font-family: 'DM Sans', sans-serif;
  outline: none; color: var(--text-primary);
  transition: border-color var(--transition);
}
.search-row input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
#previewCanvas {
  background: var(--bg); border-radius: var(--radius-md);
  min-height: 400px; position: relative; overflow: hidden;
  background-size: contain; background-repeat: no-repeat;
  background-position: center;
  border: 1px solid var(--border-light);
}
.vs-modal-footer {
  display: flex; gap: 10px; justify-content: flex-end;
  margin-top: 16px;
}

/* ── Spinner ─────────────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
.fa-spin { animation: spin 1s linear infinite; }
</style>
</head>
<body>

<!-- ── Top bar ───────────────────────────────────────────── -->
<div class="designer-topbar">
  <div class="designer-topbar-left">
    <a href="dashboard.php" class="back-btn">
      <i class="fas fa-arrow-left"></i> Dashboard
    </a>
    <h1><i class="fas fa-certificate"></i> Certificate Template Designer</h1>
  </div>
  <div class="designer-topbar-right">
    <button class="btn btn-ghost btn-sm" id="previewBtn">
      <i class="fas fa-eye"></i> Preview
    </button>
    <button class="btn btn-ghost btn-sm" id="loadBtn">
      <i class="fas fa-cloud-download-alt"></i> Load Saved
    </button>
    <button class="btn btn-primary btn-sm" id="saveBtn">
      <i class="fas fa-save"></i> Save Template
    </button>
  </div>
</div>

<!-- ── 3-column editor ───────────────────────────────────── -->
<div class="editor-wrap">

  <!-- LEFT: Toolbox -->
  <div class="toolbox">
    <div class="toolbox-heading"><i class="fas fa-th-list" style="margin-right:5px"></i> Fields</div>
    <div class="field-chip" draggable="true" data-field="certificate_number"><i class="fas fa-hashtag"></i> Certificate No</div>
    <div class="field-chip" draggable="true" data-field="candidate_name"><i class="fas fa-user"></i> Candidate Name</div>
    <div class="field-chip" draggable="true" data-field="email"><i class="fas fa-envelope"></i> Email</div>
    <div class="field-chip" draggable="true" data-field="mobile_no"><i class="fas fa-phone"></i> Mobile No</div>
    <div class="field-chip" draggable="true" data-field="college"><i class="fas fa-university"></i> College</div>
    <div class="field-chip" draggable="true" data-field="internship_course"><i class="fas fa-graduation-cap"></i> Course</div>
    <div class="field-chip" draggable="true" data-field="start_date"><i class="fas fa-calendar-plus"></i> Start Date</div>
    <div class="field-chip" draggable="true" data-field="end_date"><i class="fas fa-calendar-check"></i> End Date</div>
  </div>

  <!-- CENTRE: Canvas -->
  <div class="canvas-area" id="canvasArea">
    <input type="file" id="bgFileInput" accept=".png,.jpg,.jpeg" style="display:none">

    <div class="upload-zone" id="uploadZone">
      <i class="fas fa-cloud-upload-alt"></i>
      <p><strong>Click or drag &amp; drop</strong> your certificate background image here</p>
      <small>PNG or JPG · recommended 1754 × 1240 px (A4 landscape)</small>
      <button class="btn btn-primary btn-sm" style="margin-top:16px" onclick="document.getElementById('bgFileInput').click()">
        <i class="fas fa-upload"></i> Select Image
      </button>
    </div>

    <div id="certificateCanvas"></div>
  </div>

  <!-- RIGHT: Properties -->
  <div class="props-panel" id="propsPanel">
    <div class="props-heading"><i class="fas fa-sliders-h" style="margin-right:5px"></i> Field Properties</div>

    <div class="no-sel-msg" id="noSelMsg">
      <i class="fas fa-mouse-pointer"></i>
      Click a field on the canvas to edit its properties
    </div>

    <div id="propControls" style="display:none">
      <div class="prop-group">
        <label>Field Type</label>
        <input type="text" id="pFieldType" disabled>
      </div>
      <div class="prop-group">
        <label>Font Family</label>
        <select id="pFontFamily">
          <option value="'Plus Jakarta Sans',sans-serif">Plus Jakarta Sans</option>
          <option value="'DM Sans',sans-serif">DM Sans</option>
          <option value="Arial,sans-serif">Arial</option>
          <option value="'Times New Roman',serif">Times New Roman</option>
          <option value="Georgia,serif">Georgia</option>
          <option value="'Courier New',monospace">Courier New</option>
          <option value="Verdana,sans-serif">Verdana</option>
        </select>
      </div>
      <div class="prop-group">
        <label>Font Size</label>
        <div class="prop-size-row">
          <input type="range" id="pFontSize" min="8" max="80" value="18" style="flex:1">
          <span class="prop-size-val"><span id="pFontSizeVal">18</span>px</span>
        </div>
      </div>
      <div class="prop-group">
        <label>Font Weight</label>
        <select id="pFontWeight">
          <option value="400">Regular</option>
          <option value="500">Medium</option>
          <option value="600">Semi-Bold</option>
          <option value="700">Bold</option>
          <option value="800">Extra Bold</option>
        </select>
      </div>
      <div class="prop-group">
        <label>Text Color</label>
        <input type="color" id="pTextColor" value="#1F2D3D">
      </div>
      <div class="prop-group">
        <label>Text Align</label>
        <select id="pTextAlign">
          <option value="left">Left</option>
          <option value="center">Center</option>
          <option value="right">Right</option>
        </select>
      </div>
      <hr class="prop-divider">
      <div class="prop-group">
        <button class="btn btn-danger btn-sm" id="deleteFieldBtn" style="width:100%;justify-content:center">
          <i class="fas fa-trash"></i> Remove Field
        </button>
      </div>
    </div>
  </div>

</div>

<!-- ── Preview Modal ─────────────────────────────────────── -->
<div class="vs-modal-overlay" id="previewModal">
  <div class="vs-modal">
    <div class="vs-modal-header">
      <h2><i class="fas fa-certificate"></i> Certificate Preview</h2>
      <button class="vs-modal-close" id="closeModal"><i class="fas fa-times"></i></button>
    </div>
    <div class="search-row">
      <input type="text" id="searchInput" placeholder="Enter certificate number or student email…">
      <button class="btn btn-primary btn-sm" id="searchBtn"><i class="fas fa-search"></i> Search</button>
    </div>
    <div id="previewCanvas"></div>
    <div class="vs-modal-footer">
      <button class="btn btn-ghost btn-sm" id="printBtn"><i class="fas fa-print"></i> Print</button>
      <button class="btn btn-primary btn-sm" id="downloadPdfBtn"><i class="fas fa-file-pdf"></i> Download PDF</button>
    </div>
  </div>
</div>

<script>
const { jsPDF } = window.jspdf;

/* ── State ─────────────────────────────────────────────── */
let templateConfig = { background: null, fields: [] };
let selectedEl     = null;
let dragOffsetX    = 0, dragOffsetY = 0;
let isDragging     = false;

const canvas     = document.getElementById('certificateCanvas');
const uploadZone = document.getElementById('uploadZone');
const bgInput    = document.getElementById('bgFileInput');
const noSelMsg   = document.getElementById('noSelMsg');
const propCtrl   = document.getElementById('propControls');

/* ── Background upload ──────────────────────────────────── */
bgInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    uploadZone.style.display = 'none';
    canvas.style.display = 'block';
    canvas.style.backgroundImage = `url(${ev.target.result})`;
    const img = new Image();
    img.onload = () => {
      canvas.style.width  = '860px';
      canvas.style.height = Math.round(860 / (img.naturalWidth / img.naturalHeight)) + 'px';
    };
    img.src = ev.target.result;
    templateConfig.background = ev.target.result;
  };
  reader.readAsDataURL(file);
});

uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.style.borderColor = 'var(--primary)'; });
uploadZone.addEventListener('dragleave', () => { uploadZone.style.borderColor = 'var(--border)'; });
uploadZone.addEventListener('drop', e => {
  e.preventDefault(); uploadZone.style.borderColor = 'var(--border)';
  const f = e.dataTransfer.files[0];
  if (f && f.type.startsWith('image/')) {
    const dt = new DataTransfer(); dt.items.add(f);
    bgInput.files = dt.files;
    bgInput.dispatchEvent(new Event('change'));
  }
});
uploadZone.addEventListener('click', () => bgInput.click());

/* ── Field toolbox drag-to-canvas ───────────────────────── */
document.querySelectorAll('.field-chip').forEach(chip => {
  chip.addEventListener('dragstart', e => {
    e.dataTransfer.setData('fieldType', chip.dataset.field);
    chip.classList.add('dragging');
  });
  chip.addEventListener('dragend', () => chip.classList.remove('dragging'));
});

canvas.addEventListener('dragover', e => e.preventDefault());
canvas.addEventListener('drop', e => {
  e.preventDefault();
  const fieldType = e.dataTransfer.getData('fieldType');
  if (!fieldType || !templateConfig.background) {
    if (!templateConfig.background)
      Swal.fire({ icon: 'warning', title: 'No background', text: 'Upload a certificate background image first.', confirmButtonColor: '#4DA8FF' });
    return;
  }
  const rect = canvas.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;
  const cfg = { type: fieldType, x, y, fontSize: 18, fontFamily: "'Plus Jakarta Sans',sans-serif", fontWeight: '700', color: '#1F2D3D', textAlign: 'left' };
  addFieldToCanvas(cfg);
  templateConfig.fields.push(cfg);
});

/* ── Create field element ───────────────────────────────── */
function labelFor(type) {
  const map = {
    certificate_number: 'CERT-2024-001', candidate_name: 'Full Name',
    email: 'email@example.com', mobile_no: '9876543210',
    college: 'College Name', internship_course: 'Web Development',
    start_date: '01 Jan 2024', end_date: '31 Mar 2024'
  };
  return map[type] || `{${type}}`;
}

function addFieldToCanvas(cfg) {
  const el = document.createElement('div');
  el.className       = 'field-element';
  el.dataset.type    = cfg.type;
  el.textContent     = labelFor(cfg.type);
  el.style.left      = cfg.x + 'px';
  el.style.top       = cfg.y + 'px';
  el.style.fontSize  = cfg.fontSize + 'px';
  el.style.fontFamily = cfg.fontFamily;
  el.style.fontWeight = cfg.fontWeight;
  el.style.color     = cfg.color;
  el.style.textAlign = cfg.textAlign || 'left';

  el.addEventListener('mousedown', e => {
    e.stopPropagation();
    selectField(el);
    isDragging = true;
    const r = el.getBoundingClientRect();
    dragOffsetX = e.clientX - r.left;
    dragOffsetY = e.clientY - r.top;
  });
  el.addEventListener('click', e => { e.stopPropagation(); selectField(el); });

  canvas.appendChild(el);
  cfg._el = el;
  return el;
}

document.addEventListener('mousemove', e => {
  if (!isDragging || !selectedEl) return;
  const c = canvas.getBoundingClientRect();
  let x = e.clientX - c.left - dragOffsetX;
  let y = e.clientY - c.top  - dragOffsetY;
  x = Math.max(0, Math.min(x, c.width  - selectedEl.offsetWidth));
  y = Math.max(0, Math.min(y, c.height - selectedEl.offsetHeight));
  selectedEl.style.left = x + 'px';
  selectedEl.style.top  = y + 'px';
});
document.addEventListener('mouseup', () => {
  if (isDragging) { isDragging = false; syncFieldConfig(selectedEl); }
});
canvas.addEventListener('click', () => {
  if (selectedEl) { selectedEl.classList.remove('selected'); selectedEl = null; }
  propCtrl.style.display = 'none';
  noSelMsg.style.display = '';
});

/* ── Select field ───────────────────────────────────────── */
function selectField(el) {
  if (selectedEl) selectedEl.classList.remove('selected');
  selectedEl = el;
  el.classList.add('selected');
  noSelMsg.style.display = 'none';
  propCtrl.style.display = 'block';

  document.getElementById('pFieldType').value          = el.dataset.type;
  document.getElementById('pFontSize').value           = parseInt(el.style.fontSize);
  document.getElementById('pFontSizeVal').textContent  = parseInt(el.style.fontSize);
  document.getElementById('pFontFamily').value         = el.style.fontFamily;
  document.getElementById('pFontWeight').value         = el.style.fontWeight;
  document.getElementById('pTextColor').value          = rgbToHex(el.style.color) || '#1F2D3D';
  document.getElementById('pTextAlign').value          = el.style.textAlign || 'left';
}

/* ── Property panel listeners ───────────────────────────── */
document.getElementById('pFontSize').addEventListener('input', function () {
  document.getElementById('pFontSizeVal').textContent = this.value;
  if (selectedEl) { selectedEl.style.fontSize = this.value + 'px'; syncFieldConfig(selectedEl); }
});
['pFontFamily', 'pFontWeight', 'pTextAlign'].forEach(id => {
  document.getElementById(id).addEventListener('change', function () {
    if (!selectedEl) return;
    const map = { pFontFamily: 'fontFamily', pFontWeight: 'fontWeight', pTextAlign: 'textAlign' };
    selectedEl.style[map[id]] = this.value;
    syncFieldConfig(selectedEl);
  });
});
document.getElementById('pTextColor').addEventListener('input', function () {
  if (selectedEl) { selectedEl.style.color = this.value; syncFieldConfig(selectedEl); }
});
document.getElementById('deleteFieldBtn').addEventListener('click', () => {
  if (!selectedEl) return;
  templateConfig.fields = templateConfig.fields.filter(f => f._el !== selectedEl);
  selectedEl.remove();
  selectedEl = null;
  propCtrl.style.display = 'none';
  noSelMsg.style.display = '';
});

/* ── Sync DOM → config ──────────────────────────────────── */
function syncFieldConfig(el) {
  const f = templateConfig.fields.find(f => f._el === el);
  if (!f) return;
  f.x = parseInt(el.style.left);   f.y = parseInt(el.style.top);
  f.fontSize = parseInt(el.style.fontSize);
  f.fontFamily = el.style.fontFamily;
  f.fontWeight = el.style.fontWeight;
  f.color = el.style.color;
  f.textAlign = el.style.textAlign;
}

/* ── Save template ──────────────────────────────────────── */
document.getElementById('saveBtn').addEventListener('click', async () => {
  if (!templateConfig.background)
    return Swal.fire({ icon: 'warning', title: 'No background', text: 'Upload a background image first.', confirmButtonColor: '#4DA8FF' });
  if (templateConfig.fields.length === 0)
    return Swal.fire({ icon: 'warning', title: 'No fields', text: 'Drag at least one field onto the certificate.', confirmButtonColor: '#4DA8FF' });

  const toSave = {
    background:   templateConfig.background,
    canvasWidth:  canvas.offsetWidth,
    canvasHeight: canvas.offsetHeight,
    fields: templateConfig.fields.map(f => ({
      type: f.type, x: f.x, y: f.y,
      fontSize: f.fontSize, fontFamily: f.fontFamily,
      fontWeight: f.fontWeight, color: f.color, textAlign: f.textAlign
    }))
  };

  try {
    const r = await fetch('save_template.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(toSave)
    });
    const d = await r.json();
    if (d.success)
      Swal.fire({ icon: 'success', title: 'Template Saved!', text: 'Students can now generate their certificates.', timer: 2200, showConfirmButton: false });
    else
      Swal.fire({ icon: 'error', title: 'Save Failed', text: d.message, confirmButtonColor: '#EF4444' });
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Network Error', text: e.message });
  }
});

/* ── Load template ──────────────────────────────────────── */
document.getElementById('loadBtn').addEventListener('click', loadTemplate);

async function loadTemplate() {
  try {
    const r = await fetch('save_template.php');
    const d = await r.json();
    if (!d.success) {
      Swal.fire({ icon: 'info', title: 'No saved template', text: 'Design and save a template first.', confirmButtonColor: '#4DA8FF' });
      return;
    }
    applyTemplate(d.config);
    Swal.fire({ icon: 'success', title: 'Template Loaded', timer: 1500, showConfirmButton: false });
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Load Failed', text: e.message });
  }
}

function applyTemplate(cfg) {
  templateConfig = { background: cfg.background, fields: [] };
  canvas.querySelectorAll('.field-element').forEach(e => e.remove());
  uploadZone.style.display = 'none';
  canvas.style.display = 'block';
  canvas.style.backgroundImage = `url(${cfg.background})`;
  canvas.style.width  = (cfg.canvasWidth  || 860) + 'px';
  canvas.style.height = (cfg.canvasHeight || 600) + 'px';
  cfg.fields.forEach(f => {
    const fieldCfg = { ...f };
    addFieldToCanvas(fieldCfg);
    templateConfig.fields.push(fieldCfg);
  });
}

/* ── Preview modal ──────────────────────────────────────── */
document.getElementById('previewBtn').addEventListener('click', () => {
  if (!templateConfig.background)
    return Swal.fire({ icon: 'warning', title: 'No template', text: 'Upload a background image first.', confirmButtonColor: '#4DA8FF' });
  document.getElementById('previewModal').classList.add('open');
});
document.getElementById('closeModal').addEventListener('click', () => document.getElementById('previewModal').classList.remove('open'));
document.getElementById('searchBtn').addEventListener('click', searchAndRender);
document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') searchAndRender(); });

async function searchAndRender() {
  const term = document.getElementById('searchInput').value.trim();
  if (!term)
    return Swal.fire({ icon: 'warning', title: 'Enter a search term', text: 'Certificate number or email required.', confirmButtonColor: '#4DA8FF' });

  const btn = document.getElementById('searchBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching…';
  btn.disabled = true;

  try {
    const r = await fetch(`../api/get_certificate.php?search=${encodeURIComponent(term)}`);
    const d = await r.json();
    if (d.success) renderPreview(d.data);
    else Swal.fire({ icon: 'error', title: 'Not Found', text: d.message, confirmButtonColor: '#EF4444' });
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Error', text: e.message });
  } finally {
    btn.innerHTML = '<i class="fas fa-search"></i> Search';
    btn.disabled = false;
  }
}

function formatDate(str) {
  if (!str) return '';
  const d = new Date(str);
  return isNaN(d) ? str : d.toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
}

function renderPreview(data) {
  const pc = document.getElementById('previewCanvas');
  pc.innerHTML = '';
  pc.style.backgroundImage    = `url(${templateConfig.background})`;
  pc.style.backgroundSize     = 'contain';
  pc.style.backgroundRepeat   = 'no-repeat';
  pc.style.backgroundPosition = 'center';
  pc.style.width  = (canvas.offsetWidth  || 860) + 'px';
  pc.style.height = (canvas.offsetHeight || 600) + 'px';

  templateConfig.fields.forEach(f => {
    const el = document.createElement('div');
    el.style.cssText = `position:absolute;left:${f.x}px;top:${f.y}px;font-size:${f.fontSize}px;font-family:${f.fontFamily};font-weight:${f.fontWeight};color:${f.color};text-align:${f.textAlign || 'left'};white-space:nowrap`;
    let val = data[f.type] || `{${f.type}}`;
    if (f.type === 'start_date' || f.type === 'end_date') val = formatDate(val);
    el.textContent = val;
    pc.appendChild(el);
  });
  pc.id = 'certToDownload';
}

document.getElementById('printBtn').addEventListener('click', () => window.print());

document.getElementById('downloadPdfBtn').addEventListener('click', async () => {
  const el = document.getElementById('certToDownload');
  if (!el || !el.style.backgroundImage)
    return Swal.fire({ icon: 'warning', title: 'No preview', text: 'Search for a student first.', confirmButtonColor: '#4DA8FF' });

  const btn = document.getElementById('downloadPdfBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';
  btn.disabled = true;

  try {
    const clone = el.cloneNode(true);
    clone.id = '';
    clone.style.position = 'fixed';
    clone.style.left = '-9999px';
    clone.style.top  = '0';
    document.body.appendChild(clone);
    const cvs = await html2canvas(clone, { scale: 2, useCORS: true, allowTaint: true, logging: false });
    document.body.removeChild(clone);
    const pdf = new jsPDF('landscape', 'px', 'a4');
    const w   = pdf.internal.pageSize.getWidth();
    const h   = pdf.internal.pageSize.getHeight();
    pdf.addImage(cvs.toDataURL('image/png'), 'PNG', 0, 0, w, h);
    pdf.save('certificate_preview.pdf');
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'PDF Error', text: e.message });
  } finally {
    btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download PDF';
    btn.disabled = false;
  }
});

/* ── Helper: rgb → hex ──────────────────────────────────── */
function rgbToHex(rgb) {
  if (!rgb || rgb.startsWith('#')) return rgb;
  const m = rgb.match(/\d+/g);
  if (!m) return '#000000';
  return '#' + m.slice(0, 3).map(n => parseInt(n).toString(16).padStart(2, '0')).join('');
}
</script>
</body>
</html>