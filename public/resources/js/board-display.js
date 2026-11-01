(function () {
  const company = String(window.__BOARD_COMPANY__ || "").trim();
  const titleEl = document.getElementById("board-display-title");
  const subtitleEl = document.getElementById("board-display-subtitle");
  const clockEl = document.getElementById("board-display-clock");
  const bodyEl = document.getElementById("board-display-body");
  const errorEl = document.getElementById("board-display-error");

  let companyTimezone = null;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function statusClass(status) {
    if (status === "overdue") return "board-row-overdue";
    if (status === "soon") return "board-row-soon";
    return "board-row-upcoming";
  }

  function typeBadgeClass(type) {
    if (type === "treatment" || type === "surgery") return "board-type-treatment";
    if (type === "anesthesia") return "board-type-anesthesia";
    return "board-type-room";
  }

  function tickClock() {
    if (!clockEl || !companyTimezone) return;
    try {
      clockEl.textContent = new Intl.DateTimeFormat(undefined, {
        timeZone: companyTimezone,
        weekday: "short",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
        second: "2-digit",
        hour12: true,
      }).format(new Date());
    } catch {
      clockEl.textContent = "";
    }
  }

  async function markDone(entryId, buttonEl) {
    if (!company || !entryId) return;
    if (buttonEl) {
      buttonEl.disabled = true;
      buttonEl.textContent = "…";
    }
    try {
      const res = await fetch("/api/board/public/complete", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ company, entry_id: entryId }),
      });
      const data = await res.json();
      if (!data.ok) {
        throw new Error(data.message || "Could not mark done.");
      }
      await refresh();
    } catch (err) {
      if (buttonEl) {
        buttonEl.disabled = false;
        buttonEl.textContent = "Done";
      }
      if (errorEl) {
        errorEl.textContent = err.message || "Could not mark done.";
        errorEl.classList.remove("hidden");
      }
    }
  }

  function renderRows(entries) {
    if (!bodyEl) return;
    if (!entries.length) {
      bodyEl.innerHTML =
        '<tr><td colspan="5" class="muted board-display-empty">No appointments on the board right now.</td></tr>';
      return;
    }

    bodyEl.innerHTML = entries
      .map((row) => {
        const typeLabel = escapeHtml(row.appointment_type_label || "Room");
        const timeLabel = escapeHtml(row.appointment_time_display || row.appointment_display || "");
        return `<tr class="${statusClass(row.status)}" data-entry-id="${row.id}">
          <td>${escapeHtml(row.patient_name)}</td>
          <td><span class="board-type-badge ${typeBadgeClass(row.appointment_type)}">${typeLabel}</span></td>
          <td>${escapeHtml(row.assigned_name)}</td>
          <td class="board-time-cell">${timeLabel}</td>
          <td><button type="button" class="board-done-btn ghost" data-done-id="${row.id}">Done</button></td>
        </tr>`;
      })
      .join("");

    bodyEl.querySelectorAll("[data-done-id]").forEach((btn) => {
      btn.addEventListener("click", () => {
        markDone(Number(btn.getAttribute("data-done-id")), btn);
      });
    });
  }

  async function refresh() {
    if (!company) {
      if (errorEl) {
        errorEl.textContent =
          "Missing company code. Open this page with ?company=YOUR_CODE (find it under Admin → Company).";
        errorEl.classList.remove("hidden");
      }
      if (subtitleEl) subtitleEl.textContent = "Company not specified";
      renderRows([]);
      return;
    }

    try {
      const res = await fetch(
        `/api/board/public?company=${encodeURIComponent(company)}`,
        { headers: { Accept: "application/json" } }
      );
      const data = await res.json();
      if (!data.ok) {
        throw new Error(data.message || "Could not load board.");
      }

      companyTimezone = data.timezone || "UTC";
      if (titleEl && data.tenant_name) titleEl.textContent = data.tenant_name;
      if (subtitleEl) {
        subtitleEl.textContent = "Live schedule — tap Done when finished";
      }
      if (errorEl) errorEl.classList.add("hidden");
      renderRows(data.entries || []);
      tickClock();
    } catch (err) {
      if (errorEl) {
        errorEl.textContent = err.message || "Could not load board.";
        errorEl.classList.remove("hidden");
      }
    }
  }

  tickClock();
  setInterval(tickClock, 1000);
  refresh();
  setInterval(refresh, 30000);
})();
