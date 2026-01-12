(() => {
  "use strict";

  const $ = (id) => document.getElementById(id);

  const state = {
    isAuthed: false,
    isAdmin: false,
    receiverOffices: [],
    dashboard: { inbox: [], outbox: [] },
    dashTimer: null,
    popupTimer: null,
  };

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function showPopup(message, isSuccess = true) {
    const popup = $("popup");
    if (!popup) return;
    popup.textContent = String(message ?? "");
    popup.style.background = isSuccess ? "#333" : "#b22222";
    popup.style.display = "block";
    if (state.popupTimer) clearTimeout(state.popupTimer);
    state.popupTimer = setTimeout(() => (popup.style.display = "none"), 3000);
  }

  async function postForm(endpoint, formEl) {
    const formData = new FormData(formEl);
    const res = await fetch(endpoint, { method: "POST", body: formData });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  function activateTab(tabId) {
    const buttons = document.querySelectorAll(".tab-btn");
    const tabs = document.querySelectorAll(".tab");

    buttons.forEach((b) => b.classList.remove("active"));
    tabs.forEach((t) => t.classList.remove("active"));

    document.querySelector(`.tab-btn[data-tab="${tabId}"]`)?.classList.add("active");
    $(tabId)?.classList.add("active");
  }

  function setAuthedUI(isAuthed, isAdmin = false) {
    state.isAuthed = Boolean(isAuthed);
    state.isAdmin = Boolean(isAdmin);

    const sendBtn = $("sendTabBtn");
    const receiveBtn = $("receiveTabBtn");
    const trackBtn = $("trackTabBtn");
    const dashBtn = $("dashboardTabBtn");
    if (sendBtn) sendBtn.disabled = !state.isAuthed;
    if (receiveBtn) receiveBtn.disabled = !state.isAuthed;
    if (trackBtn) trackBtn.disabled = !state.isAuthed;
    if (dashBtn) dashBtn.disabled = !state.isAuthed;

    const adminBtn = $("adminTabBtn");
    if (adminBtn) {
      adminBtn.disabled = !(state.isAuthed && state.isAdmin);
      adminBtn.style.display = state.isAuthed && state.isAdmin ? "" : "none";
    }

    const loginTabBtn = $("loginTabBtn");
    if (loginTabBtn) loginTabBtn.style.display = state.isAuthed ? "none" : "";

    const logoutBtn = $("logoutBtn");
    if (logoutBtn) logoutBtn.style.display = state.isAuthed ? "" : "none";

    activateTab(state.isAuthed ? "dashboard" : "login");
  }

  function badge(status) {
    const s = String(status || "");
    const map = {
      delivered: { cls: "badge badge-ok", label: "DELIVERED" },
      in_transit: { cls: "badge badge-warn", label: "IN TRANSIT" },
      cancelled: { cls: "badge badge-neutral", label: "CANCELLED" },
      rejected: { cls: "badge badge-bad", label: "REJECTED" },
      archived: { cls: "badge badge-neutral", label: "ARCHIVED" },
    };
    const v = map[s] || { cls: "badge badge-neutral", label: s.toUpperCase() || "UNKNOWN" };
    return `<span class="${v.cls}">${v.label}</span>`;
  }

  // ---------- Status / lifecycle modal ----------
  async function setDocumentStatus(fileId, status, note) {
    const id = String(fileId || "").trim();
    const st = String(status || "").trim();
    const n = String(note || "").trim();
    if (!id || !st) throw new Error("Missing fileId/status.");

    const fd = new FormData();
    fd.append("fileID", id);
    fd.append("status", st);
    if (n) fd.append("note", n);

    const res = await fetch("documentSetStatus.php", { method: "POST", body: fd });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Status update failed.");
    return data;
  }

  const statusModal = {
    el: $("statusModal"),
    form: $("statusModalForm"),
    closeBtn: $("statusModalCloseBtn"),
    cancelBtn: $("statusModalCancelBtn"),
    saveBtn: $("statusModalSaveBtn"),
    fileId: $("statusModalFileId"),
    status: $("statusModalStatus"),
    note: $("statusModalNote"),
    hint: $("statusModalHint"),
    _busy: false,
    _ctx: null, // { fileId, allowedStatuses: [] }
  };

  function openStatusModal(fileId, defaultStatus, allowedStatuses, hintText) {
    if (!statusModal.el) return;

    statusModal._ctx = { fileId: String(fileId || "").trim(), allowedStatuses: allowedStatuses || [] };

    if (statusModal.fileId) statusModal.fileId.value = statusModal._ctx.fileId;
    if (statusModal.note) statusModal.note.value = "";
    if (statusModal.hint) statusModal.hint.textContent = hintText || "";

    const options = [
      ["in_transit", "In Transit"],
      ["delivered", "Delivered"],
      ["cancelled", "Cancelled"],
      ["rejected", "Rejected"],
      ["archived", "Archived"],
    ];

    if (statusModal.status) {
      statusModal.status.innerHTML = options
        .filter(([v]) => (statusModal._ctx.allowedStatuses.length ? statusModal._ctx.allowedStatuses.includes(v) : true))
        .map(([v, label]) => `<option value="${v}">${label}</option>`)
        .join("");

      const def = String(defaultStatus || "").trim();
      if (def && [...statusModal.status.options].some((o) => o.value === def)) {
        statusModal.status.value = def;
      }
    }

    statusModal.el.style.display = "flex";
    statusModal.el.setAttribute("aria-hidden", "false");
    statusModal.status?.focus?.();
  }

  function closeStatusModal() {
    if (!statusModal.el) return;
    statusModal.el.style.display = "none";
    statusModal.el.setAttribute("aria-hidden", "true");
    statusModal._ctx = null;
    statusModal._busy = false;
    if (statusModal.saveBtn) statusModal.saveBtn.disabled = false;
  }

  statusModal.closeBtn?.addEventListener("click", closeStatusModal);
  statusModal.cancelBtn?.addEventListener("click", closeStatusModal);
  statusModal.el?.addEventListener("click", (e) => {
    if (e.target === statusModal.el) closeStatusModal();
  });

  statusModal.form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (statusModal._busy || !statusModal._ctx) return;

    const fileId = statusModal._ctx.fileId;
    const newStatus = statusModal.status?.value || "";
    const note = statusModal.note?.value || "";

    statusModal._busy = true;
    if (statusModal.saveBtn) statusModal.saveBtn.disabled = true;

    try {
      await setDocumentStatus(fileId, newStatus, note);
      showPopup("Status updated.", true);
      closeStatusModal();

      await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);
      if (state.isAdmin) {
        await Promise.allSettled([loadAdminDocuments(), loadOfficeStats(), loadAdminActivity()]);
      }
    } catch (err) {
      showPopup(err.message || "Status update failed.", false);
    } finally {
      statusModal._busy = false;
      if (statusModal.saveBtn) statusModal.saveBtn.disabled = false;
    }
  });

  // ---------- Receiver offices ----------
  function renderReceiverChoices(filterText = "") {
    const select = $("receiverOfficeSelect");
    const datalist = $("receiverOfficeDatalist");
    if (!select || !datalist) return;

    const filter = String(filterText || "").trim().toLowerCase();
    const items = state.receiverOffices.filter((o) => !o.isMe);
    const filtered = filter ? items.filter((o) => o.username.toLowerCase().includes(filter)) : items;

    select.innerHTML =
      `<option value="">-- Select receiver office --</option>` +
      filtered
        .map((o) => {
          const suffix = o.isAdmin ? " (admin)" : "";
          return `<option value="${esc(o.username)}">${esc(o.username)}${suffix}</option>`;
        })
        .join("");

    datalist.innerHTML = items.map((o) => `<option value="${esc(o.username)}"></option>`).join("");
  }

  async function loadReceiverOffices() {
    const select = $("receiverOfficeSelect");
    if (select) select.innerHTML = `<option value="">Loading...</option>`;

    const res = await fetch("listOffices.php");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load offices.");

    state.receiverOffices = data?.data?.offices || [];
    renderReceiverChoices($("receiverOfficeSearch")?.value || "");
  }

  $("receiverOfficeSelect")?.addEventListener("change", (e) => {
    const v = e.target.value || "";
    const input = $("receiverUsernameSend");
    if (input && v) input.value = v;
  });

  $("receiverOfficeSearch")?.addEventListener("input", (e) => {
    const v = e.target.value || "";
    renderReceiverChoices(v);
    const exact = state.receiverOffices.find((o) => !o.isMe && o.username.toLowerCase() === v.trim().toLowerCase());
    const input = $("receiverUsernameSend");
    if (input && exact) input.value = exact.username;
  });

  $("receiverOfficeSearch")?.addEventListener("change", (e) => {
    const v = (e.target.value || "").trim();
    const input = $("receiverUsernameSend");
    if (input && v) input.value = v;
  });

  // ---------- Dashboard ----------
  function dashboardActionsForInboxRow(d) {
    const status = String(d?.status || "");
    const fileId = esc(d?.fileId || "");
    const btns = [];

    if (status === "in_transit") {
      btns.push(`<button type="button" class="btn-small status-action-btn" data-action="delivered" data-fileid="${fileId}">Deliver</button>`);
      btns.push(`<button type="button" class="btn-small btn-danger status-action-btn" data-action="rejected" data-fileid="${fileId}">Reject</button>`);
    }
    if (state.isAdmin && status !== "archived") {
      btns.push(`<button type="button" class="btn-small btn-muted status-action-btn" data-action="archived" data-fileid="${fileId}">Archive</button>`);
    }
    return btns.length ? btns.join(" ") : `<span class="muted">—</span>`;
  }

  function dashboardActionsForOutboxRow(d) {
    const status = String(d?.status || "");
    const fileId = esc(d?.fileId || "");
    const btns = [];

    if (status === "in_transit") {
      btns.push(`<button type="button" class="btn-small btn-danger status-action-btn" data-action="cancelled" data-fileid="${fileId}">Cancel</button>`);
    }
    if (state.isAdmin && status !== "archived") {
      btns.push(`<button type="button" class="btn-small btn-muted status-action-btn" data-action="archived" data-fileid="${fileId}">Archive</button>`);
    }
    return btns.length ? btns.join(" ") : `<span class="muted">—</span>`;
  }

  function applyDashboardFilter(rows) {
    const q = ($("dashSearch")?.value || "").trim().toLowerCase();
    const st = ($("dashStatus")?.value || "").trim();

    return rows.filter((d) => {
      const hay = `${d.fileId || ""} ${d.documentName || ""}`.toLowerCase();
      const okQ = !q || hay.includes(q);
      const okS = !st || String(d.status || "") === st;
      return okQ && okS;
    });
  }

  function renderDashboardTables() {
    const inboxTbody = $("dashInboxTbody");
    const outboxTbody = $("dashOutboxTbody");
    if (!inboxTbody || !outboxTbody) return;

    const inbox = applyDashboardFilter(state.dashboard.inbox);
    const outbox = applyDashboardFilter(state.dashboard.outbox);

    inboxTbody.innerHTML = inbox.length
      ? inbox
          .map(
            (d) => `
        <tr class="clickable-row" data-fileid="${esc(d.fileId)}">
          <td class="mono" data-copy="${esc(d.fileId)}">${esc(d.fileId)}</td>
          <td>${esc(d.documentName)}</td>
          <td>${badge(d.status)}</td>
          <td class="mono">${esc(d.sourceOffice)}</td>
          <td>${esc(d.createdAt)}</td>
          <td>${esc(d.deliveredAt || "-")}</td>
          <td>${dashboardActionsForInboxRow(d)}</td>
        </tr>
      `
          )
          .join("")
      : `<tr><td colspan="7">No inbox documents.</td></tr>`;

    outboxTbody.innerHTML = outbox.length
      ? outbox
          .map(
            (d) => `
        <tr class="clickable-row" data-fileid="${esc(d.fileId)}">
          <td class="mono" data-copy="${esc(d.fileId)}">${esc(d.fileId)}</td>
          <td>${esc(d.documentName)}</td>
          <td>${badge(d.status)}</td>
          <td class="mono">${esc(d.receiverOffice)}</td>
          <td>${esc(d.createdAt)}</td>
          <td>${esc(d.deliveredAt || "-")}</td>
          <td>${dashboardActionsForOutboxRow(d)}</td>
        </tr>
      `
          )
          .join("")
      : `<tr><td colspan="7">No outbox documents.</td></tr>`;
  }

  async function loadMyDashboard() {
    const inboxTbody = $("dashInboxTbody");
    const outboxTbody = $("dashOutboxTbody");
    const statsBox = $("dashStats");
    if (!inboxTbody || !outboxTbody || !statsBox) return;

    inboxTbody.innerHTML = `<tr><td colspan="7">Loading...</td></tr>`;
    outboxTbody.innerHTML = `<tr><td colspan="7">Loading...</td></tr>`;

    const res = await fetch("listMyDocuments.php");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load dashboard.");

    const stats = data?.data?.stats || {};
    state.dashboard.inbox = data?.data?.inbox || [];
    state.dashboard.outbox = data?.data?.outbox || [];

    statsBox.innerHTML = `
      <div class="dash-stat">Inbox: <b>${stats.inboxTotal ?? 0}</b> (delivered: <b>${stats.inboxDelivered ?? 0}</b>)</div>
      <div class="dash-stat">Outbox: <b>${stats.outboxTotal ?? 0}</b> (delivered: <b>${stats.outboxDelivered ?? 0}</b>)</div>
    `;

    renderDashboardTables();
  }

  function bindDashboardActions(tbodyId) {
    const tbody = $(tbodyId);
    if (!tbody) return;

    tbody.addEventListener("click", (e) => {
      const btn = e.target.closest(".status-action-btn");
      if (!btn) return;

      e.preventDefault();
      e.stopPropagation();

      const fileId = btn.getAttribute("data-fileid") || "";
      const action = btn.getAttribute("data-action") || "";

      let allowed = [];
      let hint = "";

      if (action === "delivered") {
        allowed = ["delivered"];
        hint = "Receiver action (only when In Transit).";
      } else if (action === "rejected") {
        allowed = ["rejected"];
        hint = "Receiver action (only when In Transit).";
      } else if (action === "cancelled") {
        allowed = ["cancelled"];
        hint = "Sender action (only when In Transit).";
      } else if (action === "archived") {
        allowed = ["archived"];
        hint = "Admin action.";
      } else {
        allowed = [action];
      }

      openStatusModal(fileId, action, allowed, hint);
    });
  }

  bindDashboardActions("dashInboxTbody");
  bindDashboardActions("dashOutboxTbody");

  $("dashSearch")?.addEventListener("input", renderDashboardTables);
  $("dashStatus")?.addEventListener("change", renderDashboardTables);

  $("dashAutoRefresh")?.addEventListener("change", async (e) => {
    const on = Boolean(e.target.checked);
    if (state.dashTimer) clearInterval(state.dashTimer);
    state.dashTimer = null;

    if (on) {
      state.dashTimer = setInterval(async () => {
        if (!state.isAuthed) return;
        await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);
      }, 15000);
    }
  });

  $("dashRefreshBtn")?.addEventListener("click", async () => {
    try {
      await loadMyDashboard();
      await loadMyActivity();
      showPopup("Refreshed.", true);
    } catch (e) {
      showPopup(e.message || "Refresh failed.", false);
    }
  });

  // ---------- Track ----------
  function setText(id, value) {
    const el = $(id);
    if (el) el.textContent = value ?? "";
  }

  function hideTrackResult() {
    const box = $("trackResult");
    if (box) box.style.display = "none";
    const tbody = $("trackEventsTbody");
    if (tbody) tbody.innerHTML = `<tr><td colspan="5">No history loaded.</td></tr>`;
  }

  function showTrackResult(data) {
    const d = data?.data || {};
    setText("trackStatus", String(d.status || "").toUpperCase());
    setText("trackFileId", d.fileId);
    setText("trackDocumentName", d.documentName);
    setText("trackDocumentType", d.documentType);
    setText("trackReferringTo", d.referringTo || "(none)");
    setText("trackSourceOffice", d.sourceOffice);
    setText("trackReceiverOffice", d.receiverOffice);
    setText("trackCreatedAt", d.createdAt);
    setText("trackDeliveredAt", d.deliveredAt || "(not yet)");

    const events = Array.isArray(d.events) ? d.events : [];
    const tbody = $("trackEventsTbody");
    if (tbody) {
      tbody.innerHTML = events.length
        ? events
            .map((e) => {
              const fromTo = e.fromStatus || e.toStatus ? `${esc(e.fromStatus || "-")} → ${esc(e.toStatus || "-")}` : "—";
              return `
                <tr>
                  <td>${esc(e.createdAt)}</td>
                  <td>${esc(e.type)}</td>
                  <td class="mono">${esc(e.actor)}</td>
                  <td class="mono">${fromTo}</td>
                  <td>${esc(e.note || "")}</td>
                </tr>
              `;
            })
            .join("")
        : `<tr><td colspan="5">No events found.</td></tr>`;
    }

    const box = $("trackResult");
    if (box) box.style.display = "block";
  }

  async function trackById(fileId) {
    const id = String(fileId || "").trim();
    if (!id) return;

    activateTab("track");
    const input = $("fileID");
    if (input) input.value = id;

    hideTrackResult();

    const fd = new FormData();
    fd.append("fileID", id);

    const res = await fetch("trackDocument.php", { method: "POST", body: fd });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Track failed.");

    showTrackResult(data);
  }

  function bindClickToTrack(tbodyId) {
    const tbody = $(tbodyId);
    if (!tbody) return;

    tbody.addEventListener("click", (e) => {
      if (e.target.closest(".status-action-btn")) return;
      if (e.target.closest("[data-copy]")) return;

      const tr = e.target.closest('tr[data-fileid]');
      if (!tr) return;
      trackById(tr.getAttribute("data-fileid")).catch((err) => showPopup(err.message || "Track failed.", false));
    });
  }

  bindClickToTrack("dashInboxTbody");
  bindClickToTrack("dashOutboxTbody");
  bindClickToTrack("adminDocsTbody");
  bindClickToTrack("activityTbody");
  bindClickToTrack("adminActivityTbody");

  $("portalForm-track")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    hideTrackResult();
    try {
      const data = await postForm("trackDocument.php", e.target);
      showTrackResult(data);
      showPopup("Tracking loaded.", true);
    } catch (err) {
      showPopup(err.message || "Track failed.", false);
    }
  });

  $("trackClearBtn")?.addEventListener("click", () => {
    hideTrackResult();
    $("portalForm-track")?.reset();
  });

  $("trackCopyBtn")?.addEventListener("click", async () => {
    const summary = [
      `File ID: ${$("trackFileId")?.textContent || ""}`,
      `Status: ${$("trackStatus")?.textContent || ""}`,
      `Document: ${$("trackDocumentName")?.textContent || ""}`,
      `Type: ${$("trackDocumentType")?.textContent || ""}`,
      `From: ${$("trackSourceOffice")?.textContent || ""}`,
      `To: ${$("trackReceiverOffice")?.textContent || ""}`,
      `Created: ${$("trackCreatedAt")?.textContent || ""}`,
      `Delivered: ${$("trackDeliveredAt")?.textContent || ""}`,
    ].join("\n");

    try {
      await navigator.clipboard?.writeText(summary);
      showPopup("Copied tracking summary.", true);
    } catch {
      showPopup("Copy failed.", false);
    }
  });

  // ---------- Receive lookup + quick actions ----------
  async function receiveLookup() {
    const fileId = ($("receiveFileID")?.value || "").trim();
    if (!fileId) throw new Error("Enter a File ID.");

    const fd = new FormData();
    fd.append("fileID", fileId);

    const res = await fetch("trackDocument.php", { method: "POST", body: fd });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Lookup failed.");

    const d = data?.data || {};
    $("receivePreview") && ($("receivePreview").style.display = "block");

    setText("receivePreviewStatus", String(d.status || "").toUpperCase());
    setText("receivePreviewFileId", d.fileId);
    setText("receivePreviewDoc", d.documentName);
    setText("receivePreviewType", d.documentType);
    setText("receivePreviewFrom", d.sourceOffice);
    setText("receivePreviewCreated", d.createdAt);

    $("receiveDeliverBtn")?.setAttribute("data-fileid", d.fileId || fileId);
    $("receiveRejectBtn")?.setAttribute("data-fileid", d.fileId || fileId);

    return data;
  }

  $("receiveLookupBtn")?.addEventListener("click", async () => {
    try {
      await receiveLookup();
      showPopup("Lookup loaded.", true);
    } catch (e) {
      showPopup(e.message || "Lookup failed.", false);
    }
  });

  // Use the same status modal for preview buttons
  ["receiveDeliverBtn", "receiveRejectBtn"].forEach((id) => {
    $(id)?.addEventListener("click", (e) => {
      e.preventDefault();
      const btn = e.currentTarget;
      const fileId = btn.getAttribute("data-fileid") || "";
      const action = btn.getAttribute("data-action") || "";

      const allowed = action ? [action] : [];
      const hint = "Receiver action (only when In Transit).";
      openStatusModal(fileId, action, allowed, hint);
    });
  });

  // ---------- Activity ----------
  async function loadMyActivity() {
    const tbody = $("activityTbody");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5">Loading...</td></tr>`;
    const res = await fetch("listMyActivity.php");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load activity.");

    const events = data?.data?.events || [];
    tbody.innerHTML = events.length
      ? events
          .map(
            (e) => `
        <tr class="clickable-row" data-fileid="${esc(e.fileId)}">
          <td>${esc(e.createdAt)}</td>
          <td>${esc(e.type)}</td>
          <td class="mono" data-copy="${esc(e.fileId)}">${esc(e.fileId)}</td>
          <td>${esc(e.documentName)}</td>
          <td class="mono">${esc(e.actor)}</td>
        </tr>
      `
          )
          .join("")
      : `<tr><td colspan="5">No activity yet.</td></tr>`;
  }

  $("activityRefreshBtn")?.addEventListener("click", async () => {
    try {
      await loadMyActivity();
      showPopup("Activity refreshed.", true);
    } catch (e) {
      showPopup(e.message || "Failed.", false);
    }
  });

  // click-to-copy
  document.addEventListener("click", async (ev) => {
    const el = ev.target.closest("[data-copy]");
    if (!el) return;
    const txt = el.getAttribute("data-copy") || "";
    try {
      await navigator.clipboard.writeText(txt);
      showPopup("Copied.", true);
    } catch {
      showPopup("Copy failed.", false);
    }
  });

  // ---------- Admin ----------
  function currentAdminDocsParams() {
    const q = $("adminDocsQ")?.value?.trim() || "";
    const username = $("adminDocsUser")?.value?.trim() || "";
    const status = $("adminDocsStatus")?.value || "";
    const fromDate = $("adminDocsFrom")?.value || "";
    const toDate = $("adminDocsTo")?.value || "";

    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (username) params.set("username", username);
    if (status) params.set("status", status);
    if (fromDate) params.set("fromDate", fromDate);
    if (toDate) params.set("toDate", toDate);
    return params;
  }

  async function loadAdminDocuments() {
    const tbody = $("adminDocsTbody");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="8">Loading...</td></tr>`;
    const params = currentAdminDocsParams();

    const res = await fetch(`adminListDocuments.php?${params.toString()}`);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load documents.");

    const docs = data?.data?.documents || [];
    tbody.innerHTML = docs.length
      ? docs
          .map(
            (d) => `
        <tr class="clickable-row" data-fileid="${esc(d.fileId)}">
          <td class="mono" data-copy="${esc(d.fileId)}">${esc(d.fileId)}</td>
          <td>${esc(d.documentName)}</td>
          <td>${esc(d.documentType)}</td>
          <td>${badge(d.status)}</td>
          <td class="mono">${esc(d.sourceOffice)}</td>
          <td class="mono">${esc(d.receiverOffice)}</td>
          <td>${esc(d.createdAt)}</td>
          <td>${esc(d.deliveredAt || "-")}</td>
        </tr>
      `
          )
          .join("")
      : `<tr><td colspan="8">No documents found.</td></tr>`;
  }

  async function loadOfficeStats() {
    const tbody = $("adminOfficeStatsTbody");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7">Loading...</td></tr>`;
    const res = await fetch("adminOfficeStats.php");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load office stats.");

    const rows = data?.data?.rows || [];
    tbody.innerHTML = rows.length
      ? rows
          .map(
            (r) => `
        <tr>
          <td class="mono">${esc(r.username)}</td>
          <td>${r.sentTotal}</td>
          <td>${r.sentDelivered}</td>
          <td>${r.sentInTransit}</td>
          <td>${r.recvTotal}</td>
          <td>${r.recvDelivered}</td>
          <td>${r.recvInTransit}</td>
        </tr>
      `
          )
          .join("")
      : `<tr><td colspan="7">No offices found.</td></tr>`;
  }

  async function loadAdminActivity() {
    const tbody = $("adminActivityTbody");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7">Loading...</td></tr>`;
    const res = await fetch("adminListActivity.php");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load system activity.");

    const events = data?.data?.events || [];
    tbody.innerHTML = events.length
      ? events
          .map(
            (e) => `
        <tr class="clickable-row" data-fileid="${esc(e.fileId)}">
          <td>${esc(e.createdAt)}</td>
          <td>${esc(e.type)}</td>
          <td class="mono" data-copy="${esc(e.fileId)}">${esc(e.fileId)}</td>
          <td>${esc(e.documentName)}</td>
          <td class="mono">${esc(e.actor)}</td>
          <td class="mono">${esc(e.fromStatus || "-")} → ${esc(e.toStatus || "-")}</td>
          <td>${esc(e.note || "")}</td>
        </tr>
      `
          )
          .join("")
      : `<tr><td colspan="7">No events.</td></tr>`;
  }

  async function loadOffices() {
    const tbody = $("adminOfficesTbody");
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;

    const res = await fetch("adminListOffices.php");
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Failed to load offices.");

    const offices = data?.data?.offices || [];
    tbody.innerHTML = offices.length
      ? offices
          .map((o) => {
            const role = o.isAdmin ? "Admin" : "Office";
            const toggleLabel = o.isAdmin ? "Demote" : "Promote";
            const toggleTo = o.isAdmin ? "0" : "1";
            return `
            <tr>
              <td class="mono">${esc(o.username)}</td>
              <td>${role}</td>
              <td>${esc(o.createdAt)}</td>
              <td style="white-space:nowrap;">
                <button type="button" class="admin-action-btn" data-action="toggle" data-username="${esc(o.username)}" data-isadmin="${toggleTo}">${toggleLabel}</button>
                <button type="button" class="admin-action-btn" data-action="delete" data-username="${esc(o.username)}">Delete</button>
              </td>
            </tr>
          `;
          })
          .join("")
      : `<tr><td colspan="4">No offices found.</td></tr>`;
  }

  // Admin handlers
  $("adminDocsRefreshBtn")?.addEventListener("click", async () => {
    try {
      await loadAdminDocuments();
      showPopup("Admin documents refreshed.", true);
    } catch (e) {
      showPopup(e.message || "Failed.", false);
    }
  });

  $("adminDocsExportBtn")?.addEventListener("click", () => {
    const url = `adminExportDocumentsCsv.php?${currentAdminDocsParams().toString()}`;
    window.location.href = url;
  });

  $("adminOfficeStatsRefreshBtn")?.addEventListener("click", async () => {
    try {
      await loadOfficeStats();
      showPopup("Office stats refreshed.", true);
    } catch (e) {
      showPopup(e.message || "Failed.", false);
    }
  });

  $("adminOfficeStatsExportBtn")?.addEventListener("click", () => {
    window.location.href = "adminExportOfficeStatsCsv.php";
  });

  $("adminActivityRefreshBtn")?.addEventListener("click", async () => {
    try {
      await loadAdminActivity();
      showPopup("System activity refreshed.", true);
    } catch (e) {
      showPopup(e.message || "Failed.", false);
    }
  });

  $("adminRefreshBtn")?.addEventListener("click", async () => {
    try {
      await loadOffices();
      showPopup("Office list refreshed.", true);
    } catch (e) {
      showPopup(e.message || "Failed.", false);
    }
  });

  ["adminDocsQ", "adminDocsUser", "adminDocsStatus", "adminDocsFrom", "adminDocsTo"].forEach((id) => {
    $(id)?.addEventListener("input", () => {
      if (state.isAuthed && state.isAdmin) loadAdminDocuments().catch(() => {});
    });
    $(id)?.addEventListener("change", () => {
      if (state.isAuthed && state.isAdmin) loadAdminDocuments().catch(() => {});
    });
  });

  $("adminOfficesTbody")?.addEventListener("click", async (e) => {
    const btn = e.target.closest(".admin-action-btn");
    if (!btn) return;

    const action = btn.getAttribute("data-action");
    const username = btn.getAttribute("data-username");

    try {
      if (action === "toggle") {
        const isAdmin = btn.getAttribute("data-isadmin");
        if (!confirm(`Change admin role for "${username}"?`)) return;

        const form = new FormData();
        form.append("username", username);
        form.append("isAdmin", isAdmin);

        const res = await fetch("adminSetRole.php", { method: "POST", body: form });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || "Failed to update role.");
        showPopup(data.message || "Role updated.", true);

        await Promise.allSettled([loadOffices(), loadReceiverOffices()]);
      }

      if (action === "delete") {
        if (!confirm(`Delete office "${username}"? This cannot be undone.`)) return;

        const form = new FormData();
        form.append("username", username);

        const res = await fetch("adminDeleteOffice.php", { method: "POST", body: form });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || "Failed to delete office.");
        showPopup(data.message || "Office deleted.", true);

        await Promise.allSettled([loadOffices(), loadReceiverOffices()]);
      }
    } catch (err) {
      showPopup(err.message || "Admin action failed.", false);
    }
  });

  $("portalForm-admin-create")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = await postForm("adminCreateOffice.php", e.target);
      showPopup(data.message || "Created.", true);
      e.target.reset();
      await Promise.allSettled([loadOffices(), loadReceiverOffices()]);
    } catch (err) {
      showPopup(err.message || "Create failed.", false);
    }
  });

  $("portalForm-admin-reset")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = await postForm("adminResetPassword.php", e.target);
      showPopup(data.message || "Password updated.", true);
      e.target.reset();
      await Promise.allSettled([loadOffices(), loadReceiverOffices()]);
    } catch (err) {
      showPopup(err.message || "Reset failed.", false);
    }
  });

  // ---------- Send: QR / Tag generator ----------
  (function bindQrTagGenerator() {
    const generateBtn = $("generateBtn");
    const resetBtn = $("resetSendBtn");
    const qrImage = $("qrImage");
    const docTagDiv = $("docTag");
    const docTagInput = $("docTagInput");
    const generatedArea = $("generatedArea");
    const copyBtn = $("copyTagBtn");
    const printBtn = $("printQrBtn");
    const sendForm = $("portalForm-send");

    function makeTag() {
      const ts = Date.now().toString(36).toUpperCase();
      const rnd = Math.random().toString(36).slice(2, 8).toUpperCase();
      return `BULSU-${ts}-${rnd}`;
    }

    function setQRfor(tag) {
      if (!qrImage) return;
      const data = encodeURIComponent(tag);
      qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${data}`;
    }

    generateBtn?.addEventListener("click", () => {
      const tag = makeTag();
      if (docTagDiv) docTagDiv.textContent = tag;
      if (docTagInput) docTagInput.value = tag;
      setQRfor(tag);
      if (generatedArea) generatedArea.style.display = "flex";
    });

    resetBtn?.addEventListener("click", () => {
      if (docTagDiv) docTagDiv.textContent = "";
      if (docTagInput) docTagInput.value = "";
      if (qrImage) qrImage.src = "";
      if (generatedArea) generatedArea.style.display = "none";
      sendForm?.reset?.();
      const search = $("receiverOfficeSearch");
      if (search) search.value = "";
    });

    copyBtn?.addEventListener("click", async () => {
      const tag = docTagInput?.value || "";
      if (!tag) return;
      try {
        await navigator.clipboard?.writeText(tag);
        copyBtn.textContent = "Copied";
        setTimeout(() => (copyBtn.textContent = "Copy Tag"), 1200);
      } catch {
        showPopup("Copy failed.", false);
      }
    });

    printBtn?.addEventListener("click", () => {
      const tag = docTagInput?.value || "";
      const src = qrImage?.src || "";
      if (!tag || !src) return;

      const printHtml = `
        <!doctype html>
        <html><head><meta charset="utf-8"><title>Print QR - ${esc(tag)}</title>
        <style>
          @page { size: 8.5in 11in; margin: 0.5in; }
          html,body { height:100%; margin:0; padding:0; }
          body { display:flex; align-items:center; justify-content:center; font-family: Arial, sans-serif; }
          .sheet { width:100%; height:100%; box-sizing:border-box; display:flex; flex-direction:column; align-items:center; justify-content:center; }
          .qr { width:300px; height:300px; }
          .tag { font-family: monospace; margin-top:16px; font-size:18px; word-break:break-all; }
        </style></head>
        <body><div class="sheet">
          <img class="qr" src="${src}" alt="QR">
          <div class="tag">${esc(tag)}</div>
        </div></body></html>
      `;

      const w = window.open("", "_blank");
      if (!w) return alert("Popup blocked. Allow popups to print.");
      w.document.open();
      w.document.write(printHtml);
      w.document.close();
      w.focus();
      setTimeout(() => w.print(), 600);
    });
  })();

  // ---------- Forms: login/logout/send/receive ----------
  $("portalForm-login")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = await postForm("login.php", e.target);
      showPopup(data.message || "Logged in.", true);
      setAuthedUI(true, Boolean(data?.data?.isAdmin));

      await loadReceiverOffices();
      await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);

      if (state.isAdmin) {
        await Promise.allSettled([loadAdminDocuments(), loadOfficeStats(), loadOffices(), loadAdminActivity()]);
      }
    } catch (err) {
      showPopup(err.message || "Login failed.", false);
    }
  });

  $("logoutBtn")?.addEventListener("click", async () => {
    try {
      const res = await fetch("logout.php", { method: "POST" });
      const data = await res.json().catch(() => ({}));
      showPopup(data.message || "Logged out.", true);
    } finally {
      setAuthedUI(false, false);
      hideTrackResult();
    }
  });

  $("portalForm-send")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = await postForm("documentSend.php", e.target);
      showPopup(`Saved. File ID: ${data.data?.fileId || ""}`, true);
      await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);
      if (state.isAdmin) await loadAdminDocuments();
    } catch (err) {
      showPopup(err.message || "Send failed.", false);
    }
  });

  $("portalForm-receive")?.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const data = await postForm("documentReceive.php", e.target);
      showPopup(data.message || "Received.", true);
      await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);
      if (state.isAdmin) await loadAdminDocuments();
    } catch (err) {
      showPopup(err.message || "Receive failed.", false);
    }
  });

  // ---------- Tabs ----------
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      if (btn.disabled) return;

      const tabId = btn.getAttribute("data-tab");
      if (!tabId) return;

      activateTab(tabId);

      try {
        if (tabId === "send" && state.isAuthed) await loadReceiverOffices();

        if (tabId === "dashboard" && state.isAuthed) {
          await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);
        }

        if (tabId === "admin" && state.isAuthed && state.isAdmin) {
          await Promise.allSettled([loadAdminDocuments(), loadOfficeStats(), loadOffices(), loadAdminActivity()]);
        }
      } catch (err) {
        showPopup(err.message || "Load failed.", false);
      }
    });
  });

  // ---------- Init (session check) ----------
  (async function init() {
    try {
      const res = await fetch("me.php");
      const data = await res.json().catch(() => ({}));
      const loggedIn = Boolean(data?.data?.loggedIn);
      const isAdmin = Boolean(data?.data?.isAdmin);

      setAuthedUI(loggedIn, isAdmin);

      if (loggedIn) {
        await loadReceiverOffices();
        await Promise.allSettled([loadMyDashboard(), loadMyActivity()]);

        if (isAdmin) {
          await Promise.allSettled([loadAdminDocuments(), loadOfficeStats(), loadOffices(), loadAdminActivity()]);
        }
      }
    } catch {
      setAuthedUI(false, false);
    }
  })();
})();