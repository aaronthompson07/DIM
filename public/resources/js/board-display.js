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

  function renderRows(entries) {
    if (!bodyEl) return;
    if (!entries.length) {
      bodyEl.innerHTML =
        '<tr><td colspan="4" class="muted board-display-empty">No appointments on the board right now.</td></tr>';
      return;
    }

    bodyEl.innerHTML = entries
      .map((row) => {
        const notes = row.notes ? escapeHtml(row.notes) : "—";
        return `<tr class="${statusClass(row.status)}">
          <td>${escapeHtml(row.patient_name)}</td>
          <td>${escapeHtml(row.assigned_name)}</td>
          <td>${escapeHtml(row.appointment_display)}</td>
          <td>${notes}</td>
        </tr>`;
      })
      .join("");
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
        subtitleEl.textContent = "Live appointment schedule";
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
