(function () {
  const path = window.location.pathname;

  let footerClockTimer = null;

  function updateCompanyTimezoneFromMe(me) {
    if (me && typeof me.company_timezone === "string" && me.company_timezone) {
      window.__COMPANY_TZ__ = me.company_timezone;
    }
  }

  /** Format API completion timestamps (RFC3339) for display in the company time zone. */
  function formatCompanyDateTime(isoString) {
    if (isoString == null || isoString === "") return "";
    const d = new Date(isoString);
    if (Number.isNaN(d.getTime())) return String(isoString);
    const tz = window.__COMPANY_TZ__;
    const opts = { dateStyle: "medium", timeStyle: "short" };
    if (tz) {
      return new Intl.DateTimeFormat(undefined, { ...opts, timeZone: tz }).format(d);
    }
    return new Intl.DateTimeFormat(undefined, opts).format(d);
  }

  function setupFooterClock() {
    const el = document.getElementById("footer-company-time");
    if (!el) return;
    function tick() {
      const tz = window.__COMPANY_TZ__;
      if (!tz) {
        el.textContent = "";
        return;
      }
      try {
        const now = new Date();
        el.textContent = new Intl.DateTimeFormat(undefined, {
          timeZone: tz,
          weekday: "short",
          month: "short",
          day: "numeric",
          hour: "numeric",
          minute: "2-digit",
          second: "2-digit",
          hour12: true,
        }).format(now);
      } catch {
        el.textContent = "";
      }
    }
    tick();
    if (footerClockTimer) clearInterval(footerClockTimer);
    footerClockTimer = setInterval(tick, 1000);
  }

  let footerWeatherTimer = null;

  function setupFooterWeather() {
    const el = document.getElementById("footer-company-weather");
    const sep = document.getElementById("footer-weather-sep");
    if (!el) return;
    if (footerWeatherTimer) {
      clearInterval(footerWeatherTimer);
      footerWeatherTimer = null;
    }
    async function tick() {
      try {
        const r = await get("/api/weather/current");
        if (!r.ok || !r.configured) {
          el.textContent = "";
          el.classList.add("hidden");
          if (sep) sep.classList.add("hidden");
          return;
        }
        const parts = [];
        if (r.temp_f != null && !Number.isNaN(Number(r.temp_f))) {
          parts.push(`${Math.round(Number(r.temp_f))}°F`);
        }
        const desc = r.description != null ? String(r.description).trim() : "";
        if (desc) parts.push(desc);
        if (parts.length === 0) {
          el.textContent = "";
          el.classList.add("hidden");
          if (sep) sep.classList.add("hidden");
          return;
        }
        el.textContent = parts.join(" · ");
        el.classList.remove("hidden");
        if (sep) sep.classList.remove("hidden");
      } catch {
        el.textContent = "";
        el.classList.add("hidden");
        if (sep) sep.classList.add("hidden");
      }
    }
    tick();
    footerWeatherTimer = setInterval(tick, 10 * 60 * 1000);
  }

  function refreshFooterBar() {
    setupFooterClock();
    setupFooterWeather();
  }

  function applyResolvedTheme(theme) {
    const t = theme === "dark" ? "dark" : "light";
    document.body.classList.remove("theme-light", "theme-dark");
    document.body.classList.add(t === "dark" ? "theme-dark" : "theme-light");
  }

  function post(url, data) {
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data || {}),
    }).then((r) => r.json());
  }

  function get(url) {
    return fetch(url).then((r) => r.json());
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function taskEl(pfx, suffix) {
    return document.getElementById(pfx + suffix);
  }

  function taskWeekdaySelector(pfx) {
    if (pfx === "inline-") return ".inline-task-wd";
    if (pfx === "dash-") return ".dash-task-wd";
    return ".task-wd";
  }

  function getSelectedWeekdaysFor(wdSel) {
    return [...document.querySelectorAll(`${wdSel}:checked`)]
      .map((c) => Number(c.getAttribute("data-day")))
      .sort((a, b) => a - b);
  }

  function setWeekdayCheckboxesFor(days, wdSel) {
    document.querySelectorAll(wdSel).forEach((c) => {
      const d = Number(c.getAttribute("data-day"));
      c.checked = days.includes(d);
    });
  }

  function updateRecurrenceFieldsFor(pfx) {
    const rec = taskEl(pfx, "task-recurrence");
    if (!rec) return;
    const v = rec.value;
    const showWd = v === "weekly" || v === "biweekly";
    const showAnchor = v === "biweekly" || v === "quarterly";
    const showM = v === "monthly" || v === "quarterly";
    const showMonthlyWeekday = v === "monthly" || v === "quarterly";
    const wb = taskEl(pfx, "task-weekdays-block");
    const ar = taskEl(pfx, "task-anchor-row");
    const mwr = taskEl(pfx, "task-monthly-weekday-row");
    const dl = taskEl(pfx, "task-dom-label");
    const dm = taskEl(pfx, "task-dom");
    if (wb) wb.classList.toggle("hidden", !showWd);
    if (ar) ar.classList.toggle("hidden", !showAnchor);
    if (mwr) mwr.classList.toggle("hidden", !showMonthlyWeekday);
    if (dl) dl.classList.toggle("hidden", !showM);
    if (dm) dm.classList.toggle("hidden", !showM);
  }

  function resetTaskFormFor(pfx) {
    const e = (s) => taskEl(pfx, s);
    if (!e("task-edit-id")) return;
    e("task-edit-id").value = "";
    e("task-title").value = "";
    e("task-details").value = "";
    e("task-recurrence").value = "none";
    setWeekdayCheckboxesFor([1], taskWeekdaySelector(pfx));
    const ad = e("task-anchor-date");
    if (ad) ad.value = "";
    e("task-dom").value = "1";
    const mw = e("task-monthly-weekday");
    if (mw) mw.value = "";
    e("task-assignee").value = "";
    e("task-infinity").checked = false;
    e("task-active").checked = true;
    const sl = e("task-slot");
    const pr = e("task-priority");
    if (sl?.options.length) sl.selectedIndex = 0;
    if (pr?.options.length) pr.selectedIndex = 0;
    updateRecurrenceFieldsFor(pfx);
  }

  function fillTaskFormInto(t, pfx) {
    const e = (s) => taskEl(pfx, s);
    e("task-edit-id").value = String(t.id);
    e("task-title").value = t.title || "";
    e("task-details").value = t.details || "";
    const tp = e("task-priority");
    const ts = e("task-slot");
    if (t.priority_id && tp && [...tp.options].some((o) => o.value === String(t.priority_id))) {
      tp.value = String(t.priority_id);
    } else if (tp?.options.length) tp.selectedIndex = 0;
    if (t.time_slot_id && ts) ts.value = String(t.time_slot_id);
    e("task-recurrence").value = t.recurrence_type || "none";
    let wd = [];
    if (t.recurrence_weekdays != null && String(t.recurrence_weekdays).trim() !== "") {
      wd = String(t.recurrence_weekdays)
        .split(",")
        .map((x) => Number(x.trim()))
        .filter((n) => !Number.isNaN(n) && n >= 0 && n <= 6);
    } else if (t.recurrence_weekday != null && t.recurrence_weekday !== "") {
      wd = [Number(t.recurrence_weekday)];
    }
    setWeekdayCheckboxesFor(wd.length ? wd : [1], taskWeekdaySelector(pfx));
    const ad = e("task-anchor-date");
    if (ad) {
      ad.value =
        t.recurrence_anchor_date != null && String(t.recurrence_anchor_date).trim() !== ""
          ? String(t.recurrence_anchor_date).slice(0, 10)
          : "";
    }
    const mw = e("task-monthly-weekday");
    if (mw) {
      mw.value =
        t.recurrence_weekday != null && t.recurrence_weekday !== ""
          ? String(t.recurrence_weekday)
          : "";
    }
    e("task-dom").value = t.recurrence_day_of_month != null ? String(t.recurrence_day_of_month) : "1";
    e("task-assignee").value = t.assigned_user_id ? String(t.assigned_user_id) : "";
    e("task-infinity").checked = Number(t.is_infinity) === 1;
    e("task-active").checked = Number(t.is_active) === 1;
    updateRecurrenceFieldsFor(pfx);
  }

  function buildTaskPayload(pfx) {
    const e = (s) => taskEl(pfx, s);
    const rt = e("task-recurrence").value;
    const wdSel = taskWeekdaySelector(pfx);
    const monthlyWeekdayRaw = e("task-monthly-weekday")?.value ?? "";
    const monthlyWeekday =
      (rt === "monthly" || rt === "quarterly") && monthlyWeekdayRaw !== ""
        ? Number(monthlyWeekdayRaw)
        : null;
    return {
      title: e("task-title").value.trim(),
      details: e("task-details").value,
      priority_id: Number(e("task-priority")?.value || 0),
      time_slot_id: Number(e("task-slot")?.value || 0),
      recurrence_type: rt,
      recurrence_weekdays: rt === "weekly" || rt === "biweekly" ? getSelectedWeekdaysFor(wdSel) : null,
      recurrence_weekday: monthlyWeekday,
      recurrence_anchor_date:
        rt === "biweekly" || rt === "quarterly" ? e("task-anchor-date")?.value || null : null,
      recurrence_day_of_month:
        rt === "monthly" || rt === "quarterly" ? Number(e("task-dom").value) : null,
      assigned_user_id: e("task-assignee").value ? Number(e("task-assignee").value) : null,
      is_infinity: e("task-infinity").checked ? 1 : 0,
      is_active: e("task-active").checked ? 1 : 0,
    };
  }

  function setupLogin() {
    const companyForm = document.getElementById("company-form");
    if (!companyForm) return;

    const message = document.getElementById("login-message");
    const demoBtn = document.getElementById("demo-btn");

    function setLoginMessage(text, kind = "error") {
      if (!message) return;
      message.textContent = text || "";
      message.classList.remove("auth-message--error", "auth-message--success");
      if (!text) return;
      message.classList.add(kind === "success" ? "auth-message--success" : "auth-message--error");
    }

    companyForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(companyForm);
      const result = await post("/api/auth/company-login", {
        company_username: String(formData.get("company_username") || ""),
        password: String(formData.get("password") || ""),
      });
      if (!result.ok) {
        setLoginMessage(result.message || "Company login failed.", "error");
        return;
      }
      window.location.href = "/dashboard";
    });

    demoBtn?.addEventListener("click", async () => {
      const result = await post("/api/auth/demo-login");
      if (!result.ok) {
        setLoginMessage("Demo user login unavailable.", "error");
        return;
      }
      window.location.href = "/dashboard";
    });
  }

  function setupRegister() {
    if (path !== "/register") return;
    const form = document.getElementById("register-form");
    const msg = document.getElementById("register-message");
    if (!form) return;

    function setRegisterMessage(text, kind = "error") {
      if (!msg) return;
      msg.textContent = text || "";
      msg.classList.remove("auth-message--error", "auth-message--success");
      if (!text) return;
      msg.classList.add(kind === "success" ? "auth-message--success" : "auth-message--error");
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      setRegisterMessage("");
      const fd = new FormData(form);
      const result = await post("/api/auth/register-tenant", {
        company_name: String(fd.get("company_name") || "").trim(),
        company_username: String(fd.get("company_username") || "").trim().toLowerCase(),
        company_password: String(fd.get("company_password") || ""),
        owner_email: String(fd.get("owner_email") || "").trim(),
        admin_display_name: String(fd.get("admin_display_name") || "").trim(),
        admin_pin: String(fd.get("admin_pin") || ""),
        timezone: String(fd.get("timezone") || "UTC").trim() || "UTC",
      });
      if (!result.ok) {
        setRegisterMessage(result.message || "Registration failed.", "error");
        return;
      }
      const cu = String(fd.get("company_username") || "").trim().toLowerCase();
      const pin = String(fd.get("admin_pin") || "");
      const loginCo = await post("/api/auth/company-login", {
        company_username: cu,
        password: String(fd.get("company_password") || ""),
      });
      if (!loginCo.ok) {
        setRegisterMessage("Company created. Sign in with your new company login.", "success");
        return;
      }
      const loginPin = await post("/api/auth/pin-login", { pin });
      if (!loginPin.ok) {
        setRegisterMessage("Company created. Sign in and use your PIN on the dashboard.", "success");
        window.location.href = "/login";
        return;
      }
      window.location.href = "/dashboard";
    });
  }

  function setupDashboard() {
    if (path !== "/dashboard") return;

    const userDisplayName = document.getElementById("user-display-name");
    const switchUserBtn = document.getElementById("switch-user-btn");
    const adminWrap = document.getElementById("admin-link-wrap");
    const userAuthModal = document.getElementById("user-auth-modal");
    const closeUserAuthModal = document.getElementById("close-user-auth-modal");
    const userForm = document.getElementById("dashboard-user-form");
    const pinInput = document.getElementById("dashboard-pin-input");
    const keypad = document.getElementById("dashboard-keypad");
    const userMessage = document.getElementById("dashboard-user-message");
    const taskBoard = document.getElementById("task-board");
    const systemMessagesSection = document.getElementById("system-messages-section");
    const systemMessagesList = document.getElementById("system-messages-list");
    const weatherAlertsSection = document.getElementById("weather-alerts-section");
    const weatherAlertsList = document.getElementById("weather-alerts-list");
    const weatherAlertsMeta = document.getElementById("weather-alerts-meta");
    const completedTodayBtn = document.getElementById("show-completed");
    const assignedToMeBtn = document.getElementById("show-assigned-to-me");
    const completedTasks = document.getElementById("completed-tasks");
    const completedModal = document.getElementById("completed-modal");
    const assignedTasksList = document.getElementById("assigned-tasks-list");
    const assignedModal = document.getElementById("assigned-modal");
    const reportsLinkWrap = document.getElementById("reports-link-wrap");
    const addTaskBtn = document.getElementById("add-task-btn");
    const dashTaskModal = document.getElementById("dash-task-modal");
    const dashTaskForm = document.getElementById("dash-task-form");

    function setCompletedTodayButton(count) {
      if (!completedTodayBtn) return;
      const n = Number(count) || 0;
      completedTodayBtn.textContent = `Completed today (${n})`;
    }
    function setAssignedToMeButton(count) {
      if (!assignedToMeBtn) return;
      const n = Number(count) || 0;
      assignedToMeBtn.textContent = `Assigned to me (${n})`;
    }
    let pollHandle = null;
    let weatherPollHandle = null;
    let hasUser = false;
    let lastMe = null;
    let lastBoardLimited = false;

    function updateDashboardTaskButtons() {
      const can = Boolean(hasUser && lastMe?.user?.can_edit_tasks);
      const canWrite = can && !lastBoardLimited;
      if (addTaskBtn) {
        addTaskBtn.classList.toggle("hidden", !can);
        addTaskBtn.disabled = !canWrite;
      }
    }

    async function populateDashTaskSelects() {
      const slotEl = taskEl("dash-", "task-slot");
      const priEl = taskEl("dash-", "task-priority");
      const asEl = taskEl("dash-", "task-assignee");
      if (!slotEl || !priEl || !asEl) return;
      const [slotsRes, priRes, usersRes] = await Promise.all([
        get("/api/admin/time-slots"),
        get("/api/admin/priorities"),
        get("/api/admin/tenant-users"),
      ]);
      const keepSlot = slotEl.value;
      const keepPri = priEl.value;
      const keepAs = asEl.value;
      if (slotsRes.ok && slotsRes.slots) {
        const slots = slotsRes.slots.filter((s) => Number(s.is_active) === 1);
        slotEl.innerHTML = "";
        slots.forEach((s) => {
          const opt = document.createElement("option");
          opt.value = String(s.id);
          opt.textContent = s.name;
          slotEl.appendChild(opt);
        });
        if (keepSlot && [...slotEl.options].some((o) => o.value === keepSlot)) slotEl.value = keepSlot;
        else if (slotEl.options.length) slotEl.selectedIndex = 0;
      }
      if (priRes.ok && priRes.priorities) {
        const priorities = priRes.priorities.filter((p) => Number(p.is_active) === 1);
        priEl.innerHTML = "";
        priorities.forEach((p) => {
          const opt = document.createElement("option");
          opt.value = String(p.id);
          opt.textContent = p.name;
          priEl.appendChild(opt);
        });
        if (keepPri && [...priEl.options].some((o) => o.value === keepPri)) priEl.value = keepPri;
        else if (priEl.options.length) priEl.selectedIndex = 0;
      }
      asEl.innerHTML = '<option value="">— None —</option>';
      if (usersRes.ok && usersRes.users) {
        usersRes.users.forEach((u) => {
          const opt = document.createElement("option");
          opt.value = String(u.id);
          opt.textContent = `${u.display_name} (${u.role})`;
          asEl.appendChild(opt);
        });
      }
      if (keepAs && [...asEl.options].some((o) => o.value === keepAs)) asEl.value = keepAs;
    }

    async function openDashTaskModalForEdit(taskId) {
      if (!dashTaskModal || lastBoardLimited) return;
      const heading = document.getElementById("dash-task-modal-title");
      if (heading) heading.textContent = "Edit task";
      await populateDashTaskSelects();
      const res = await get("/api/admin/tasks");
      if (!res.ok) {
        window.alert(res.message || "Could not load tasks.");
        return;
      }
      const t = (res.tasks || []).find((x) => Number(x.id) === Number(taskId));
      if (!t) {
        window.alert("Task not found.");
        return;
      }
      fillTaskFormInto(t, "dash-");
      dashTaskModal.classList.remove("hidden");
    }

    async function openDashTaskModalForNew() {
      if (!dashTaskModal || lastBoardLimited) return;
      const heading = document.getElementById("dash-task-modal-title");
      if (heading) heading.textContent = "Add task";
      await populateDashTaskSelects();
      resetTaskFormFor("dash-");
      dashTaskModal.classList.remove("hidden");
    }

    function closeDashTaskModal() {
      dashTaskModal?.classList.add("hidden");
    }

    function applyTopbarBrand(me) {
      const logo = document.getElementById("tenant-logo");
      const brandText = document.getElementById("tenant-brand-text");
      const tb = me?.tenant_brand;
      if (brandText) {
        brandText.textContent = tb?.name ? String(tb.name) : "DoItMeow";
      }
      if (logo) {
        const url = tb?.logo_url ? String(tb.logo_url).trim() : "";
        if (url) {
          logo.src = url;
          logo.alt = tb?.name ? String(tb.name) : "Logo";
          logo.classList.remove("hidden");
        } else {
          logo.removeAttribute("src");
          logo.alt = "";
          logo.classList.add("hidden");
        }
      }
    }

    function applyDashboardIntegrations(me) {
      const adSlot = document.getElementById("dashboard-ad-slot");
      const adInsHost = document.getElementById("dashboard-ad-ins");
      const trainingWrap = document.getElementById("training-link-wrap");
      if (trainingWrap) {
        trainingWrap.classList.toggle(
          "hidden",
          !(me.user && me.integrations?.features?.training_employee)
        );
      }
      if (reportsLinkWrap) {
        reportsLinkWrap.classList.toggle(
          "hidden",
          !(me.user && me.integrations?.features?.tasks_reports && me.can_view_reports)
        );
      }
      if (!me.user || !me.integrations?.show_google_ads) {
        if (adSlot) adSlot.classList.add("hidden");
        if (adInsHost) {
          adInsHost.innerHTML = "";
          delete adInsHost.dataset.loaded;
        }
      } else if (me.integrations?.show_google_ads && adSlot && adInsHost) {
        const client = me.integrations.adsense_client;
        const slot = me.integrations.adsense_slot_dashboard;
        if (client && slot) {
          adSlot.classList.remove("hidden");
          if (adInsHost.dataset.loaded !== "1") {
            adInsHost.innerHTML = "";
            const ins = document.createElement("ins");
            ins.className = "adsbygoogle";
            ins.style.display = "block";
            ins.setAttribute("data-ad-client", client);
            ins.setAttribute("data-ad-slot", slot);
            ins.setAttribute("data-ad-format", "auto");
            ins.setAttribute("data-full-width-responsive", "true");
            adInsHost.appendChild(ins);
            const runAd = () => {
              try {
                (window.adsbygoogle = window.adsbygoogle || []).push({});
              } catch (e) {
                /* ignore */
              }
              adInsHost.dataset.loaded = "1";
            };
            const existing = document.querySelector('script[src*="pagead2.googlesyndication.com"]');
            if (!existing) {
              const s = document.createElement("script");
              s.async = true;
              s.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(client)}`;
              s.crossOrigin = "anonymous";
              s.onload = runAd;
              document.head.appendChild(s);
            } else {
              runAd();
            }
          }
        }
      }
    }

    function renderSystemMessages(messages) {
      if (!systemMessagesSection || !systemMessagesList) return;
      const rows = Array.isArray(messages) ? messages : [];
      systemMessagesList.innerHTML = "";
      if (!rows.length) {
        systemMessagesSection.classList.add("hidden");
        return;
      }
      rows.forEach((m) => {
        const li = document.createElement("li");
        li.className = `system-message-item${Number(m.is_maintenance) === 1 ? " maintenance" : ""}`;
        const txt = document.createElement("div");
        txt.textContent = String(m.message_text || "");
        li.appendChild(txt);
        if (Number(m.is_maintenance) === 1) {
          const tag = document.createElement("div");
          tag.className = "muted";
          tag.style.marginTop = "6px";
          tag.style.fontSize = "12px";
          tag.textContent = "Maintenance mode is active.";
          li.appendChild(tag);
        }
        systemMessagesList.appendChild(li);
      });
      systemMessagesSection.classList.remove("hidden");
    }

    function buildKeypad(targetInput, container) {
      if (!targetInput || !container) return;
      container.innerHTML = "";
      ["1","2","3","4","5","6","7","8","9","0","<-","Clear"].forEach((key) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = key;
        btn.addEventListener("click", () => {
          if (key === "<-") targetInput.value = targetInput.value.slice(0, -1);
          else if (key === "Clear") targetInput.value = "";
          else if (targetInput.value.length < 6) targetInput.value += key;
        });
        container.appendChild(btn);
      });
    }

    function updateUserHeader() {
      if (!userDisplayName) return;
      if (hasUser) {
        userDisplayName.textContent = lastMe?.user?.display_name || "User";
        userDisplayName.classList.remove("muted");
      } else {
        userDisplayName.textContent = "Not signed in";
        userDisplayName.classList.add("muted");
      }
    }

    function setPinFeedback(text, tone = "error") {
      if (!userMessage) return;
      userMessage.textContent = text || "";
      userMessage.classList.remove("user-feedback--error", "user-feedback--success", "user-feedback--info");
      if (!text) return;
      const cls =
        tone === "success"
          ? "user-feedback--success"
          : tone === "info"
            ? "user-feedback--info"
            : "user-feedback--error";
      userMessage.classList.add(cls);
      userMessage.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }

    async function openUserPrompt(force) {
      setPinFeedback("");
      pinInput.value = "";
      if (closeUserAuthModal) closeUserAuthModal.style.display = force ? "none" : "inline-block";
      userAuthModal.classList.remove("hidden");
      pinInput.focus();
    }

    function closeUserPrompt() {
      userAuthModal.classList.add("hidden");
    }

    async function syncSession() {
      const me = await get("/api/auth/me");
      if (!me.ok || !me.company_authenticated) {
        window.location.href = "/login";
        return false;
      }
      lastMe = me;
      lastBoardLimited = Boolean(me.limited_mode);
      applyTopbarBrand(me);
      updateCompanyTimezoneFromMe(me);
      refreshFooterBar();
      if (me.resolved_theme) {
        applyResolvedTheme(me.resolved_theme);
      }

      if (me.user) {
        hasUser = true;
        updateUserHeader();
        if (adminWrap) {
          if (["owner", "super_admin", "tenant_admin", "tenant_manager"].includes(me.user.role)) {
            adminWrap.classList.remove("hidden");
          } else {
            adminWrap.classList.add("hidden");
          }
        }
        applyDashboardIntegrations(me);
        renderSystemMessages(me.system_messages || []);
        updateDashboardTaskButtons();
        return true;
      }

      hasUser = false;
      updateUserHeader();
      if (adminWrap) adminWrap.classList.add("hidden");
      applyDashboardIntegrations(me);
      renderSystemMessages(me.system_messages || []);
      updateDashboardTaskButtons();
      return false;
    }

    function stopPolling() {
      if (pollHandle) {
        clearInterval(pollHandle);
        pollHandle = null;
      }
    }

    function stopWeatherPolling() {
      if (weatherPollHandle) {
        clearInterval(weatherPollHandle);
        weatherPollHandle = null;
      }
    }

    function clearWeatherAlertsCard() {
      if (weatherAlertsSection) weatherAlertsSection.classList.add("hidden");
      if (weatherAlertsList) weatherAlertsList.innerHTML = "";
      if (weatherAlertsMeta) weatherAlertsMeta.textContent = "";
    }

    function renderWeatherAlerts(data) {
      if (!weatherAlertsSection || !weatherAlertsList) return;
      if (!data || !data.ok || !data.configured) {
        weatherAlertsSection.classList.add("hidden");
        return;
      }
      const alerts = data.alerts || [];
      if (alerts.length === 0) {
        weatherAlertsSection.classList.add("hidden");
        return;
      }
      weatherAlertsSection.classList.remove("hidden");
      if (weatherAlertsMeta) {
        const when = data.fetched_at ? `Updated ${new Date(data.fetched_at).toLocaleString()} · ` : "";
        weatherAlertsMeta.textContent = `${when}NOAA / National Weather Service`;
      }
      weatherAlertsList.innerHTML = "";
      alerts.forEach((a) => {
        const li = document.createElement("li");
        li.className = "weather-alert-item";
        const ev = document.createElement("div");
        ev.className = "weather-alert-event";
        ev.textContent = a.event || "Alert";
        li.appendChild(ev);
        if (a.headline) {
          const h = document.createElement("div");
          h.className = "weather-alert-headline";
          h.textContent = a.headline;
          li.appendChild(h);
        }
        if (a.area_desc) {
          const ar = document.createElement("div");
          ar.className = "muted weather-alert-area";
          ar.textContent = a.area_desc;
          li.appendChild(ar);
        }
        if (a.expires) {
          const ex = document.createElement("div");
          ex.className = "muted weather-alert-expires";
          ex.textContent = `Until ${new Date(a.expires).toLocaleString()}`;
          li.appendChild(ex);
        }
        if (a.url && /^https?:\/\//i.test(String(a.url))) {
          const link = document.createElement("a");
          link.href = a.url;
          link.className = "weather-alert-link";
          link.target = "_blank";
          link.rel = "noopener noreferrer";
          link.textContent = "Details on weather.gov";
          li.appendChild(link);
        }
        weatherAlertsList.appendChild(li);
      });
    }

    async function fetchWeatherAlerts() {
      if (!hasUser) return;
      const winScrollY =
        window.scrollY ??
        document.documentElement?.scrollTop ??
        document.body?.scrollTop ??
        0;
      const data = await get("/api/weather/alerts");
      if (!data.ok) return;
      renderWeatherAlerts(data);
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          window.scrollTo(0, winScrollY);
        });
      });
    }

    function startWeatherPolling() {
      stopWeatherPolling();
      if (!hasUser) return;
      fetchWeatherAlerts();
      weatherPollHandle = setInterval(fetchWeatherAlerts, 5 * 60 * 1000);
    }

    function startPollingIfNeeded() {
      stopPolling();
      if (!hasUser) return;
      pollHandle = setInterval(refresh, 5000);
      startWeatherPolling();
    }

    async function refresh() {
      if (!hasUser) return;
      const board = await get("/api/tasks/today-board");
      if (!board.ok) return;
      const winScrollY =
        window.scrollY ??
        document.documentElement?.scrollTop ??
        document.body?.scrollTop ??
        0;
      const restoreWindowScroll = () => {
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            window.scrollTo(0, winScrollY);
          });
        });
      };
      try {
      lastBoardLimited = Boolean(board.limited_mode);
      updateDashboardTaskButtons();
      renderSystemMessages(board.system_messages || lastMe?.system_messages || []);
      const todayLabel = board.today || new Date().toISOString().slice(0, 10);
      setCompletedTodayButton(board.completed);
      setAssignedToMeButton(board.assigned_to_me_open);

      const openBySlotId = {};
      (board.slot_open_counts || []).forEach((c) => {
        openBySlotId[String(c.id)] = Number(c.open) || 0;
      });

      if (!taskBoard) return;
      taskBoard.innerHTML = "";
      const slots = board.slots || [];
      const tasks = board.tasks || [];

      /* If every time-of-day column is inactive/removed, API returns no slots — but tasks can still
         exist (e.g. time_slot_id cleared). Do not hide tasks; show them in one column. */
      if (!slots.length) {
        if (!tasks.length) {
          taskBoard.innerHTML =
            "<p class='muted'>No time-of-day columns yet. An admin can add them under Admin → Time of day.</p>";
          return;
        }
        const col = document.createElement("div");
        col.className = "task-board-column task-board-column-other";
        const h = document.createElement("h3");
        h.className = "task-board-heading";
        h.textContent = `Today's tasks (${tasks.length})`;
        const ul = document.createElement("ul");
        ul.className = "task-board-list";
        tasks.forEach((t) => appendTask(ul, t));
        const hint = document.createElement("p");
        hint.className = "muted board-fallback-hint";
        hint.style.marginTop = "10px";
        hint.style.fontSize = "13px";
        hint.textContent =
          "No active time-of-day columns — tasks are grouped here. Add or turn on a column under Admin → Time of day to split the board.";
        col.appendChild(h);
        col.appendChild(ul);
        col.appendChild(hint);
        taskBoard.appendChild(col);
        return;
      }

      function appendTask(ul, task) {
        const li = document.createElement("li");
        li.className = "task-board-item";
        const barColor =
          task.priority_color && /^#[0-9A-Fa-f]{6}$/i.test(String(task.priority_color))
            ? String(task.priority_color)
            : "#94a3b8";
        li.style.borderLeft = `4px solid ${barColor}`;
        const isCarryover = task.is_infinity === 1 && task.due_date !== todayLabel;
        const main = document.createElement("div");
        main.className = "task-board-item-main";
        const titleRow = document.createElement("div");
        titleRow.className = "task-board-title-row";
        const span = document.createElement("span");
        span.className = "task-board-item-title";
        span.textContent = task.title + (isCarryover ? " (carried over)" : "");
        titleRow.appendChild(span);
        if (Number(task.is_infinity) === 1) {
          const inf = document.createElement("span");
          inf.className = "task-infinity";
          inf.textContent = "\u221E";
          inf.title = "Infinity task";
          titleRow.appendChild(inf);
        }
        main.appendChild(titleRow);
        const detText = task.details != null ? String(task.details).trim() : "";
        if (detText) {
          const det = document.createElement("div");
          det.className = "task-board-details muted";
          det.textContent = detText;
          main.appendChild(det);
        }
        if (task.assignee_name) {
          const as = document.createElement("div");
          as.className = "task-board-assignee muted";
          as.textContent = "Assigned: " + task.assignee_name;
          main.appendChild(as);
        }
        const actionsWrap = document.createElement("div");
        actionsWrap.className = "task-board-item-actions";
        const canEdit =
          Boolean(lastMe?.user?.can_edit_tasks) &&
          !lastBoardLimited &&
          task.task_id != null &&
          !Number.isNaN(Number(task.task_id));
        if (canEdit) {
          const editBtn = document.createElement("button");
          editBtn.type = "button";
          editBtn.className = "ghost task-board-edit";
          editBtn.textContent = "Edit";
          editBtn.addEventListener("click", () => openDashTaskModalForEdit(Number(task.task_id)));
          actionsWrap.appendChild(editBtn);
        }
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "task-board-done";
        btn.textContent = "Done";
        btn.disabled = Boolean(lastBoardLimited);
        btn.addEventListener("click", async () => {
          await post("/api/tasks/complete", { occurrence_id: task.id });
          refresh();
        });
        actionsWrap.appendChild(btn);
        li.appendChild(main);
        li.appendChild(actionsWrap);
        ul.appendChild(li);
      }

      slots.forEach((slot) => {
        const col = document.createElement("div");
        col.className = "task-board-column";
        const h = document.createElement("h3");
        h.className = "task-board-heading";
        const openN = openBySlotId[String(slot.id)] ?? 0;
        h.textContent = `${slot.name} (${openN})`;
        const ul = document.createElement("ul");
        ul.className = "task-board-list";
        tasks
          .filter((t) => String(t.time_slot_id) === String(slot.id))
          .forEach((t) => appendTask(ul, t));
        col.appendChild(h);
        col.appendChild(ul);
        taskBoard.appendChild(col);
      });

      const unassigned = tasks.filter((t) => {
        const sid = t.time_slot_id != null ? String(t.time_slot_id) : "";
        return !sid || !slots.some((s) => String(s.id) === sid);
      });
      if (unassigned.length) {
        const col = document.createElement("div");
        col.className = "task-board-column task-board-column-other";
        const h = document.createElement("h3");
        h.className = "task-board-heading";
        h.textContent = `Other (${unassigned.length})`;
        const ul = document.createElement("ul");
        ul.className = "task-board-list";
        unassigned.forEach((t) => appendTask(ul, t));
        col.appendChild(h);
        col.appendChild(ul);
        taskBoard.appendChild(col);
      }
    } finally {
      restoreWindowScroll();
    }
    }

    async function refreshAssignedToMeModal() {
      const data = await get("/api/tasks/assigned-to-me");
      if (!data.ok || !assignedTasksList) return;
      assignedTasksList.innerHTML = "";
      const todayLabel = data.today || new Date().toISOString().slice(0, 10);
      (data.tasks || []).forEach((task) => {
        const li = document.createElement("li");
        li.className = "assigned-modal-task";
        const main = document.createElement("div");
        main.className = "assigned-modal-task-main";
        const barColor =
          task.priority_color && /^#[0-9A-Fa-f]{6}$/i.test(String(task.priority_color))
            ? String(task.priority_color)
            : "#94a3b8";
        main.style.borderLeft = `4px solid ${barColor}`;
        main.style.paddingLeft = "10px";
        const titleRow = document.createElement("div");
        titleRow.className = "assigned-modal-task-title";
        const isCarryover = task.is_infinity === 1 && task.due_date !== todayLabel;
        titleRow.textContent = (task.title || "Task") + (isCarryover ? " (carried over)" : "");
        main.appendChild(titleRow);
        const meta = document.createElement("div");
        meta.className = "assigned-modal-task-meta muted";
        const slot = task.time_slot_name ? String(task.time_slot_name) : "Other";
        meta.textContent = slot;
        main.appendChild(meta);
        const detText = task.details != null ? String(task.details).trim() : "";
        if (detText) {
          const det = document.createElement("div");
          det.className = "muted";
          det.style.marginTop = "6px";
          det.style.fontSize = "14px";
          det.textContent = detText;
          main.appendChild(det);
        }
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "Done";
        btn.disabled = Boolean(lastMe?.limited_mode);
        btn.addEventListener("click", async () => {
          const r = await post("/api/tasks/complete", { occurrence_id: task.id });
          if (!r.ok) {
            window.alert(r.message || "Could not complete task.");
            return;
          }
          await refreshAssignedToMeModal();
          if (hasUser) await refresh();
        });
        li.appendChild(main);
        li.appendChild(btn);
        assignedTasksList.appendChild(li);
      });
      if (!(data.tasks || []).length) {
        const li = document.createElement("li");
        li.className = "muted";
        li.textContent = "No open tasks assigned to you for today.";
        assignedTasksList.appendChild(li);
      }
    }

    async function refreshCompletedTodayModal() {
      const done = await get("/api/tasks/today-completed");
      if (!done.ok || !completedTasks) return;
      completedTasks.innerHTML = "";
      (done.tasks || []).forEach((task) => {
        const li = document.createElement("li");
        li.className = "task task-completed-row";
        const main = document.createElement("span");
        main.className = "task-completed-main";
        main.textContent = `${task.title} — ${task.completed_by || "Unknown"} @ ${formatCompanyDateTime(task.completed_at)}`;
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "ghost";
        btn.textContent = "Undo";
        btn.disabled = Boolean(lastMe?.limited_mode);
        btn.addEventListener("click", async () => {
          const r = await post("/api/tasks/uncomplete", { occurrence_id: task.id });
          if (!r.ok) {
            window.alert(r.message || "Could not undo.");
            return;
          }
          await refreshCompletedTodayModal();
          if (hasUser) await refresh();
        });
        li.appendChild(main);
        li.appendChild(btn);
        completedTasks.appendChild(li);
      });
      if (!(done.tasks || []).length) {
        const li = document.createElement("li");
        li.className = "muted";
        li.textContent = "Nothing completed yet today.";
        completedTasks.appendChild(li);
      }
    }

    document.getElementById("show-completed")?.addEventListener("click", async () => {
      if (!hasUser) {
        await openUserPrompt(true);
        return;
      }
      await refreshCompletedTodayModal();
      completedModal.classList.remove("hidden");
    });

    assignedToMeBtn?.addEventListener("click", async () => {
      if (!hasUser) {
        await openUserPrompt(true);
        return;
      }
      await refreshAssignedToMeModal();
      assignedModal?.classList.remove("hidden");
    });

    document.getElementById("close-completed")?.addEventListener("click", () => {
      completedModal.classList.add("hidden");
    });

    document.getElementById("close-assigned")?.addEventListener("click", () => {
      assignedModal?.classList.add("hidden");
    });

    addTaskBtn?.addEventListener("click", async () => {
      if (!hasUser) {
        await openUserPrompt(true);
        return;
      }
      if (!lastMe?.user?.can_edit_tasks || lastBoardLimited) return;
      await openDashTaskModalForNew();
    });

    dashTaskForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!hasUser || lastBoardLimited) return;
      const id = taskEl("dash-", "task-edit-id")?.value?.trim();
      const payload = buildTaskPayload("dash-");
      const result = id
        ? await post("/api/admin/tasks/update", { id: Number(id), ...payload })
        : await post("/api/admin/tasks/create", payload);
      if (!result.ok) {
        window.alert(result.message || "Save failed.");
        return;
      }
      closeDashTaskModal();
      await refresh();
    });

    document.getElementById("dash-task-cancel")?.addEventListener("click", () => {
      closeDashTaskModal();
    });

    taskEl("dash-", "task-recurrence")?.addEventListener("change", () => {
      const iad = taskEl("dash-", "task-anchor-date");
      const type = taskEl("dash-", "task-recurrence")?.value;
      if ((type === "biweekly" || type === "quarterly") && iad && !iad.value) {
        iad.value = new Date().toISOString().slice(0, 10);
      }
      updateRecurrenceFieldsFor("dash-");
    });

    switchUserBtn?.addEventListener("click", async () => {
      if (hasUser) {
        await post("/api/auth/logout-user");
        // Full reload so ArrowChat (footer) drops the previous user’s JS/session state.
        window.location.reload();
        return;
      }
      await openUserPrompt(false);
    });

    document.getElementById("logout-all")?.addEventListener("click", async () => {
      await post("/api/auth/logout-all");
      window.location.href = "/login";
    });

    document.getElementById("theme-toggle")?.addEventListener("click", async () => {
      const next = document.body.classList.contains("theme-dark") ? "light" : "dark";
      const r = await post("/api/auth/theme-preference", { theme: next });
      if (r.ok && r.resolved_theme) {
        applyResolvedTheme(r.resolved_theme);
      } else {
        applyResolvedTheme(next);
      }
    });

    buildKeypad(pinInput, keypad);

    closeUserAuthModal?.addEventListener("click", () => {
      if (!hasUser && closeUserAuthModal?.style.display === "none") return;
      closeUserPrompt();
    });

    userForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const result = await post("/api/auth/pin-login", { pin: pinInput.value });
      if (!result.ok) {
        setPinFeedback(result.message || "PIN login failed.", "error");
        return;
      }
      // Full reload so ArrowChat autoload (footer) runs with the new user session/cookies.
      window.location.reload();
    });

    syncSession().then(async (userPresent) => {
      if (!userPresent) {
        await openUserPrompt(true);
        return;
      }
      await refresh();
      startPollingIfNeeded();
    });
  }

  function setupAdmin() {
    if (path !== "/admin") return;

    const message = document.getElementById("admin-message");
    const tabsRoot = document.getElementById("admin-tabs");
    const companyProfileForm = document.getElementById("company-profile-form");
    const coPlatformFields = document.getElementById("co-platform-fields");
    const matrixWrap = document.getElementById("feature-matrix-wrap");
    const saveMatrixBtn = document.getElementById("save-feature-matrix");
    const impersonationCard = document.getElementById("impersonation-card");
    const impersonateSelect = document.getElementById("impersonate-tenant-select");
    const impersonateGo = document.getElementById("impersonate-go");
    const impersonateClear = document.getElementById("impersonate-clear");
    const impersonationStatus = document.getElementById("impersonation-status");
    const createTenantForm = document.getElementById("create-tenant-form");
    const adminThemeToggle = document.getElementById("admin-theme-toggle");
    const taskForm = document.getElementById("task-form");
    const taskEditId = document.getElementById("task-edit-id");
    const taskTitle = document.getElementById("task-title");
    const taskDetails = document.getElementById("task-details");
    const taskPriority = document.getElementById("task-priority");
    const taskSlot = document.getElementById("task-slot");
    const taskRecurrence = document.getElementById("task-recurrence");
    const taskWeekdaysBlock = document.getElementById("task-weekdays-block");
    const taskAnchorRow = document.getElementById("task-anchor-row");
    const taskAnchorDate = document.getElementById("task-anchor-date");
    const taskDom = document.getElementById("task-dom");
    const taskDomLabel = document.getElementById("task-dom-label");
    const taskAssignee = document.getElementById("task-assignee");
    const taskInfinity = document.getElementById("task-infinity");
    const taskActive = document.getElementById("task-active");
    const taskList = document.getElementById("task-list");
    const taskNewBtn = document.getElementById("task-new");
    const slotEditorList = document.getElementById("slot-editor-list");
    const newSlotName = document.getElementById("new-slot-name");
    const newSlotAdd = document.getElementById("new-slot-add");
    const priorityEditorList = document.getElementById("priority-editor-list");
    const newPriorityName = document.getElementById("new-priority-name");
    const newPriorityColor = document.getElementById("new-priority-color");
    const newPriorityAdd = document.getElementById("new-priority-add");
    const plansEditorWrap = document.getElementById("plans-editor-wrap");
    const planCreateForm = document.getElementById("plan-create-form");
    const userListWrap = document.getElementById("user-list-wrap");
    const userCreateForm = document.getElementById("user-create-form");
    const newUserName = document.getElementById("new-user-name");
    const newUserEmail = document.getElementById("new-user-email");
    const newUserRole = document.getElementById("new-user-role");
    const newUserPin = document.getElementById("new-user-pin");
    const systemMessageForm = document.getElementById("system-message-form");
    const systemMessageText = document.getElementById("system-message-text");
    const systemMessageMaintenance = document.getElementById("system-message-maintenance");
    const systemMessageGlobal = document.getElementById("system-message-global");
    const systemMessageScopeRow = document.getElementById("system-message-scope-row");
    const systemMessageMaintenanceRow = document.getElementById("system-message-maintenance-row");
    const systemMessageMaintenanceHint = document.getElementById("system-message-maintenance-hint");
    const systemMessageList = document.getElementById("system-message-list");

    let currentConfig = null;
    let currentActorRole = "";

    function setMessage(text, tone = "info") {
      if (!message) return;
      message.textContent = text || "";
      message.classList.remove("user-feedback--error", "user-feedback--success", "user-feedback--info", "muted");
      message.classList.add("admin-footer-msg", "user-feedback");
      if (!text) return;
      const cls =
        tone === "error"
          ? "user-feedback--error"
          : tone === "success"
            ? "user-feedback--success"
            : "user-feedback--info";
      message.classList.add(cls);
      if (tone === "error") {
        message.scrollIntoView({ block: "nearest", behavior: "smooth" });
      }
    }

    function setMessageOk(ok, okText, errFallback, serverMsg) {
      const err = String(serverMsg || "").trim() || errFallback;
      setMessage(ok ? okText : err, ok ? "success" : "error");
    }

    function isTaskEditorRole(role) {
      return ["owner", "super_admin", "tenant_admin", "tenant_manager"].includes(role);
    }

    let auditLogsLoaded = false;
    let trainingAdminLoaded = false;
    let trDefsCache = [];
    let trTplsCache = [];
    let trTemplateItemOrder = [];

    function showPanel(key) {
      document.querySelectorAll(".admin-panel").forEach((p) => {
        p.classList.toggle("hidden", p.dataset.panel !== key);
      });
      document.querySelectorAll(".admin-tab").forEach((b) => {
        b.classList.toggle("active", b.dataset.tab === key);
      });
      if (key === "logs" && currentConfig?.can_view_audit_logs && !auditLogsLoaded) {
        auditLogsLoaded = true;
        loadAuditLogsTable();
      }
      if (key === "training" && currentConfig?.can_manage_training && !trainingAdminLoaded) {
        trainingAdminLoaded = true;
        loadTrainingAdminPanel();
      }
    }

    async function loadAuditLogsTable() {
      const tbody = document.querySelector("#audit-logs-table tbody");
      if (!tbody) return;
      const res = await get("/api/admin/audit-logs");
      tbody.innerHTML = "";
      if (!res.ok) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 4;
        td.className = "muted";
        td.textContent = res.message || "Unable to load logs.";
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
      }
      const rows = res.logs || [];
      rows.forEach((row) => {
        const tr = document.createElement("tr");
        const t0 = document.createElement("td");
        t0.textContent = row.created_at != null ? String(row.created_at) : "";
        const tw = document.createElement("td");
        tw.textContent =
          row.user_display_name != null && String(row.user_display_name).trim() !== ""
            ? String(row.user_display_name)
            : row.user_id != null && row.user_id !== ""
              ? `User #${row.user_id}`
              : "—";
        const t1 = document.createElement("td");
        t1.textContent = row.action != null ? String(row.action) : "";
        const t2 = document.createElement("td");
        t2.className = "audit-meta";
        const pre = document.createElement("pre");
        pre.className = "audit-meta-pre";
        pre.textContent =
          row.meta != null && typeof row.meta === "object" ? JSON.stringify(row.meta, null, 0) : "";
        t2.appendChild(pre);
        tr.appendChild(t0);
        tr.appendChild(tw);
        tr.appendChild(t1);
        tr.appendChild(t2);
        tbody.appendChild(tr);
      });
      if (!rows.length) {
        const tr = document.createElement("tr");
        const td = document.createElement("td");
        td.colSpan = 4;
        td.className = "muted";
        td.textContent = "No log entries.";
        tr.appendChild(td);
        tbody.appendChild(tr);
      }
    }

    async function fillTrainingAssignUsers() {
      const sel = document.getElementById("tr-assign-user");
      if (!sel) return;
      const keep = sel.value;
      const res = await get("/api/admin/tenant-users");
      sel.innerHTML = "";
      if (!res.ok) return;
      let users = res.users || [];
      if (currentActorRole === "tenant_manager") {
        users = users.filter((u) => u.role === "tenant_user");
      }
      users
        .filter((u) => u.is_active == null || Number(u.is_active) === 1)
        .forEach((u) => {
          const opt = document.createElement("option");
          opt.value = String(u.id);
          opt.textContent = `${u.display_name} (${u.role})`;
          sel.appendChild(opt);
        });
      if (keep && [...sel.options].some((o) => o.value === keep)) sel.value = keep;
    }

    function fillTrainingDefinitionSelects() {
      const pickDef = document.getElementById("tr-item-pick-def");
      const assignDef = document.getElementById("tr-assign-def");
      const active = trDefsCache.filter((d) => Number(d.is_active) === 1);
      if (pickDef) {
        const k = pickDef.value;
        pickDef.innerHTML = "";
        trDefsCache.forEach((d) => {
          const opt = document.createElement("option");
          opt.value = String(d.id);
          opt.textContent = `${d.title}${Number(d.is_active) ? "" : " (inactive)"}`;
          pickDef.appendChild(opt);
        });
        if (k && [...pickDef.options].some((o) => o.value === k)) pickDef.value = k;
      }
      if (assignDef) {
        const k = assignDef.value;
        assignDef.innerHTML = "";
        active.forEach((d) => {
          const opt = document.createElement("option");
          opt.value = String(d.id);
          opt.textContent = d.title;
          assignDef.appendChild(opt);
        });
        if (k && [...assignDef.options].some((o) => o.value === k)) assignDef.value = k;
      }
    }

    function syncTemplateBuilderFromCache() {
      const sel = document.getElementById("tr-items-template");
      if (!sel) return;
      const tid = Number(sel.value || 0);
      const tp = trTplsCache.find((t) => Number(t.id) === tid);
      trTemplateItemOrder =
        tp && Array.isArray(tp.items) && tp.items.length
          ? tp.items.map((i) => Number(i.definition_id))
          : [];
    }

    function trainingDefinitionTitle(defId) {
      const d = trDefsCache.find((x) => Number(x.id) === Number(defId));
      return d ? String(d.title) : `#${defId}`;
    }

    function renderTemplateOrderList() {
      const ul = document.getElementById("tr-template-items-order");
      if (!ul) return;
      ul.innerHTML = "";
      trTemplateItemOrder.forEach((defId, idx) => {
        const li = document.createElement("li");
        li.className = "tr-template-order-row";
        const lab = document.createElement("span");
        lab.className = "tr-template-order-label";
        lab.textContent = `${idx + 1}. ${trainingDefinitionTitle(defId)}`;
        const actions = document.createElement("div");
        actions.className = "row-actions tr-template-order-actions";
        const up = document.createElement("button");
        up.type = "button";
        up.className = "ghost";
        up.textContent = "Up";
        up.disabled = idx < 1;
        up.addEventListener("click", () => {
          if (idx < 1) return;
          const t = trTemplateItemOrder[idx - 1];
          trTemplateItemOrder[idx - 1] = trTemplateItemOrder[idx];
          trTemplateItemOrder[idx] = t;
          renderTemplateOrderList();
        });
        const dn = document.createElement("button");
        dn.type = "button";
        dn.className = "ghost";
        dn.textContent = "Down";
        dn.disabled = idx >= trTemplateItemOrder.length - 1;
        dn.addEventListener("click", () => {
          if (idx >= trTemplateItemOrder.length - 1) return;
          const t = trTemplateItemOrder[idx + 1];
          trTemplateItemOrder[idx + 1] = trTemplateItemOrder[idx];
          trTemplateItemOrder[idx] = t;
          renderTemplateOrderList();
        });
        const rm = document.createElement("button");
        rm.type = "button";
        rm.className = "ghost";
        rm.textContent = "Remove";
        rm.addEventListener("click", () => {
          trTemplateItemOrder.splice(idx, 1);
          renderTemplateOrderList();
        });
        actions.appendChild(up);
        actions.appendChild(dn);
        actions.appendChild(rm);
        li.appendChild(lab);
        li.appendChild(actions);
        ul.appendChild(li);
      });
    }

    function fillTrainingTemplateSelects() {
      const itemsTpl = document.getElementById("tr-items-template");
      const assignTpl = document.getElementById("tr-assign-tpl");
      const active = trTplsCache.filter((t) => Number(t.is_active) === 1);
      [itemsTpl, assignTpl].forEach((sel) => {
        if (!sel) return;
        const k = sel.value;
        sel.innerHTML = "";
        trTplsCache.forEach((t) => {
          const opt = document.createElement("option");
          opt.value = String(t.id);
          opt.textContent = `${t.name}${Number(t.is_active) ? "" : " (inactive)"}`;
          sel.appendChild(opt);
        });
        if (k && [...sel.options].some((o) => o.value === k)) sel.value = k;
      });
    }

    function renderTrainingDefList() {
      const ul = document.getElementById("tr-def-list");
      if (!ul) return;
      ul.innerHTML = "";
      trDefsCache.forEach((def) => {
        const li = document.createElement("li");
        const left = document.createElement("span");
        const st = Number(def.is_active) ? "" : " (inactive)";
        left.textContent = `${def.title} · order ${def.sort_order}${st}`;
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "Edit";
        btn.addEventListener("click", () => {
          document.getElementById("tr-def-id").value = String(def.id);
          document.getElementById("tr-def-title").value = def.title || "";
          document.getElementById("tr-def-details").value = def.details || "";
          document.getElementById("tr-def-sort").value = String(def.sort_order ?? 0);
          document.getElementById("tr-def-active").checked = Number(def.is_active) === 1;
        });
        li.appendChild(left);
        li.appendChild(btn);
        ul.appendChild(li);
      });
    }

    function renderTrainingTplList() {
      const ul = document.getElementById("tr-tpl-list");
      if (!ul) return;
      ul.innerHTML = "";
      trTplsCache.forEach((tp) => {
        const li = document.createElement("li");
        const left = document.createElement("span");
        const n = (tp.items || []).length;
        const st = Number(tp.is_active) ? "" : " (inactive)";
        left.textContent = `${tp.name}${st}${n ? ` · ${n} item(s)` : ""}`;
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "Edit";
        btn.addEventListener("click", () => {
          document.getElementById("tr-tpl-id").value = String(tp.id);
          document.getElementById("tr-tpl-name").value = tp.name || "";
          document.getElementById("tr-tpl-desc").value = tp.description || "";
          document.getElementById("tr-tpl-active").checked = Number(tp.is_active) === 1;
          const its = document.getElementById("tr-items-template");
          if (its) its.value = String(tp.id);
          syncTemplateBuilderFromCache();
          renderTemplateOrderList();
        });
        li.appendChild(left);
        li.appendChild(btn);
        ul.appendChild(li);
      });
    }

    async function loadTrainingAdminPanel() {
      const d = await get("/api/training/definitions");
      const t = await get("/api/training/templates");
      if (!d.ok || !t.ok) {
        setMessage(d.message || t.message || "Could not load training.", "error");
        return;
      }
      trDefsCache = d.definitions || [];
      trTplsCache = t.templates || [];
      renderTrainingDefList();
      renderTrainingTplList();
      fillTrainingDefinitionSelects();
      fillTrainingTemplateSelects();
      await fillTrainingAssignUsers();
      syncTemplateBuilderFromCache();
      renderTemplateOrderList();
    }

    function buildTabs(config, actorRole) {
      if (!tabsRoot) return;
      tabsRoot.innerHTML = "";
      auditLogsLoaded = false;
      trainingAdminLoaded = false;
      const tabs = [{ key: "tasks", label: "Tasks" }];
      if (config.can_manage_time_slots) tabs.push({ key: "slots", label: "Time of day" });
      if (config.can_manage_priorities) tabs.push({ key: "priorities", label: "Priorities" });
      if (config.can_manage_system_messages) tabs.push({ key: "messages", label: "Messages" });
      tabs.push({ key: "users", label: "People" });
      if (config.can_view_audit_logs) tabs.push({ key: "logs", label: "Activity logs" });
      if (config.can_manage_training) tabs.push({ key: "training", label: "Training" });
      if (config.can_manage_tenant) tabs.push({ key: "company", label: "Company" });
      if (config.can_manage_plans) tabs.push({ key: "plans", label: "Billing plans" });
      if (config.can_manage_matrix) tabs.push({ key: "matrix", label: "Feature matrix" });
      tabs.forEach((t, i) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "admin-tab" + (i === 0 ? " active" : "");
        btn.dataset.tab = t.key;
        btn.textContent = t.label;
        btn.addEventListener("click", () => showPanel(t.key));
        tabsRoot.appendChild(btn);
      });
      showPanel("tasks");
    }

    function buildMatrix(config) {
      if (!matrixWrap) return;
      matrixWrap.innerHTML = "";
      if (!config.can_manage_matrix) {
        matrixWrap.innerHTML = "<p class='muted'>Only owner/super admin can edit the subscription matrix.</p>";
        if (saveMatrixBtn) saveMatrixBtn.style.display = "none";
        return;
      }
      if (saveMatrixBtn) saveMatrixBtn.style.display = "inline-block";
      const table = document.createElement("table");
      table.className = "matrix-table";
      const thead = document.createElement("thead");
      const headRow = document.createElement("tr");
      headRow.innerHTML = "<th>Feature</th>" + config.levels.map((l) => `<th>${l.name}</th>`).join("");
      thead.appendChild(headRow);
      table.appendChild(thead);
      const tbody = document.createElement("tbody");
      config.features.forEach((feature) => {
        const row = document.createElement("tr");
        const labelCell = document.createElement("td");
        labelCell.innerHTML = `<strong>${feature.name}</strong><br><span class="muted">${feature.feature_key}</span>`;
        row.appendChild(labelCell);
        config.levels.forEach((level) => {
          const key = `${level.id}:${feature.id}`;
          const td = document.createElement("td");
          td.innerHTML = `<input type="checkbox" data-level-id="${level.id}" data-feature-id="${feature.id}" ${config.matrix[key] === 1 ? "checked" : ""}>`;
          row.appendChild(td);
        });
        tbody.appendChild(row);
      });
      table.appendChild(tbody);
      matrixWrap.appendChild(table);
    }

    const taskInlineShell = document.getElementById("task-inline-shell");
    const taskInlineWrap = document.getElementById("task-inline-wrap");
    const taskInlineForm = document.getElementById("task-inline-form");

    function getSelectedWeekdays() {
      return getSelectedWeekdaysFor(".task-wd");
    }

    function setWeekdayCheckboxes(days) {
      setWeekdayCheckboxesFor(days, ".task-wd");
    }

    function updateRecurrenceFields() {
      updateRecurrenceFieldsFor("");
    }

    function resetTaskForm() {
      resetTaskFormFor("");
    }

    function fillTaskForm(t) {
      fillTaskFormInto(t, "");
    }

    async function loadTaskSlotOptions(activeOnly) {
      const res = await get("/api/admin/time-slots");
      if (!res.ok || !taskSlot) return;
      const keep = taskSlot.value;
      taskSlot.innerHTML = "";
      const list = activeOnly ? res.slots.filter((s) => Number(s.is_active) === 1) : res.slots;
      list.forEach((s) => {
        const opt = document.createElement("option");
        opt.value = String(s.id);
        opt.textContent = s.name + (Number(s.is_active) ? "" : " (inactive)");
        taskSlot.appendChild(opt);
      });
      if (keep && [...taskSlot.options].some((o) => o.value === keep)) taskSlot.value = keep;
      else if (taskSlot.options.length) taskSlot.selectedIndex = 0;
      const inlineSlot = taskEl("inline-", "task-slot");
      if (inlineSlot && taskSlot) inlineSlot.innerHTML = taskSlot.innerHTML;
      const dashSlot = taskEl("dash-", "task-slot");
      if (dashSlot && taskSlot) dashSlot.innerHTML = taskSlot.innerHTML;
    }

    async function loadTaskPriorityOptions(activeOnly) {
      const res = await get("/api/admin/priorities");
      if (!res.ok || !taskPriority) return;
      const keep = taskPriority.value;
      taskPriority.innerHTML = "";
      const list = activeOnly ? res.priorities.filter((p) => Number(p.is_active) === 1) : res.priorities;
      list.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = String(p.id);
        opt.textContent = p.name + (Number(p.is_active) ? "" : " (inactive)");
        taskPriority.appendChild(opt);
      });
      if (keep && [...taskPriority.options].some((o) => o.value === keep)) taskPriority.value = keep;
      else if (taskPriority.options.length) taskPriority.selectedIndex = 0;
      const inlinePri = taskEl("inline-", "task-priority");
      if (inlinePri && taskPriority) inlinePri.innerHTML = taskPriority.innerHTML;
      const dashPri = taskEl("dash-", "task-priority");
      if (dashPri && taskPriority) dashPri.innerHTML = taskPriority.innerHTML;
    }

    function hideTaskInlineEditor() {
      if (!taskInlineShell || !taskInlineWrap) return;
      taskInlineShell.classList.add("hidden");
      taskInlineWrap.appendChild(taskInlineShell);
      resetTaskFormFor("inline-");
    }

    function showTaskInlineEditor(li, t) {
      if (!taskInlineShell || !taskList) return;
      const inlineSlot = taskEl("inline-", "task-slot");
      const inlinePri = taskEl("inline-", "task-priority");
      if (taskSlot && inlineSlot) inlineSlot.innerHTML = taskSlot.innerHTML;
      if (taskPriority && inlinePri) inlinePri.innerHTML = taskPriority.innerHTML;
      const inlineAs = taskEl("inline-", "task-assignee");
      if (taskAssignee && inlineAs) inlineAs.innerHTML = taskAssignee.innerHTML;
      fillTaskFormInto(t, "inline-");
      li.insertAdjacentElement("afterend", taskInlineShell);
      taskInlineShell.classList.remove("hidden");
      taskInlineShell.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }

    async function loadTenantUsers() {
      const res = await get("/api/admin/tenant-users");
      if (!res.ok) return;
      const keep = taskAssignee.value;
      taskAssignee.innerHTML = '<option value="">— None —</option>';
      res.users.forEach((u) => {
        const opt = document.createElement("option");
        opt.value = String(u.id);
        opt.textContent = `${u.display_name} (${u.role})`;
        taskAssignee.appendChild(opt);
      });
      if (keep) taskAssignee.value = keep;
      const inlineAs = taskEl("inline-", "task-assignee");
      if (inlineAs) inlineAs.innerHTML = taskAssignee.innerHTML;
      const dashAs = taskEl("dash-", "task-assignee");
      if (dashAs) dashAs.innerHTML = taskAssignee.innerHTML;
    }

    function formatTaskRecurrenceSummary(t) {
      const type = t.recurrence_type || "none";
      const names = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
      if (type === "none") return "one-off";
      if (type === "daily") return "daily";
      if (type === "monthly") {
        if (t.recurrence_weekday != null && t.recurrence_weekday !== "") {
          const d = Number(t.recurrence_weekday);
          return `monthly (first ${names[d] ?? d})`;
        }
        return `monthly (day ${t.recurrence_day_of_month ?? "?"})`;
      }
      let days = [];
      if (t.recurrence_weekdays != null && String(t.recurrence_weekdays).trim() !== "") {
        days = String(t.recurrence_weekdays)
          .split(",")
          .map((x) => Number(x.trim()))
          .filter((n) => !Number.isNaN(n));
      } else if (t.recurrence_weekday != null && t.recurrence_weekday !== "") {
        days = [Number(t.recurrence_weekday)];
      }
      const dayStr = days.map((d) => names[d] ?? d).join(", ");
      if (type === "weekly") return dayStr ? `weekly (${dayStr})` : "weekly";
      if (type === "biweekly") {
        const a = t.recurrence_anchor_date ? String(t.recurrence_anchor_date).slice(0, 10) : "";
        return dayStr ? `bi-weekly (${dayStr})${a ? ` from ${a}` : ""}` : "bi-weekly";
      }
      if (type === "quarterly") {
        const a = t.recurrence_anchor_date ? String(t.recurrence_anchor_date).slice(0, 10) : "";
        if (t.recurrence_weekday != null && t.recurrence_weekday !== "") {
          const d = Number(t.recurrence_weekday);
          return `quarterly (first ${names[d] ?? d})${a ? ` from ${a}` : ""}`;
        }
        return `quarterly (day ${t.recurrence_day_of_month ?? "?"})${a ? ` from ${a}` : ""}`;
      }
      return type;
    }

    async function loadTasks() {
      const res = await get("/api/admin/tasks");
      if (!res.ok) return;
      hideTaskInlineEditor();
      taskList.innerHTML = "";
      res.tasks.forEach((t) => {
        const li = document.createElement("li");
        li.className = "task-admin-list-row";
        const left = document.createElement("span");
        const slotLabel = t.time_slot_name || "—";
        const pri = t.priority_name || t.priority_key || "—";
        const color = t.priority_color
          ? `<span class="task-pri-dot" style="background:${t.priority_color}" title="${pri}"></span>`
          : "";
        const recLabel = formatTaskRecurrenceSummary(t);
        left.innerHTML = `${color}<strong>${t.title}</strong> <span class="muted">${slotLabel} · ${pri} · ${recLabel}</span>`;
        const actions = document.createElement("span");
        actions.className = "task-admin-row-actions";
        const btnEdit = document.createElement("button");
        btnEdit.type = "button";
        btnEdit.className = "ghost";
        btnEdit.textContent = "Edit";
        btnEdit.addEventListener("click", () => showTaskInlineEditor(li, t));
        const btnDel = document.createElement("button");
        btnDel.type = "button";
        btnDel.className = "ghost task-admin-delete";
        btnDel.textContent = "Delete";
        btnDel.addEventListener("click", async () => {
          if (
            !window.confirm(
              `Remove “${t.title || "this task"}” from the dashboard and task list? Past completions stay in reports.`
            )
          ) {
            return;
          }
          const result = await post("/api/admin/tasks/delete", { id: Number(t.id) });
          setMessageOk(result.ok, "Task removed.", "Delete failed.", result.message);
          if (result.ok) {
            hideTaskInlineEditor();
            await loadTasks();
          }
        });
        actions.appendChild(btnEdit);
        actions.appendChild(btnDel);
        li.appendChild(left);
        li.appendChild(actions);
        taskList.appendChild(li);
      });
    }

    async function loadSlotEditor() {
      if (!slotEditorList || !currentConfig?.can_manage_time_slots) return;
      const res = await get("/api/admin/time-slots");
      if (!res.ok) return;
      slotEditorList.innerHTML = "";
      res.slots.forEach((s) => {
        const li = document.createElement("li");
        li.className = "slot-editor-row";
        li.innerHTML = `
          <input type="text" class="slot-name-input" data-id="${s.id}" value="${String(s.name).replace(/"/g, "&quot;")}">
          <input type="number" class="slot-order-input" data-id="${s.id}" value="${s.sort_order}" style="width:4rem">
          <label class="inline-check"><input type="checkbox" class="slot-active-input" data-id="${s.id}" ${Number(s.is_active) ? "checked" : ""}> Active</label>
          <button type="button" class="slot-save-btn" data-id="${s.id}">Save</button>
        `;
        slotEditorList.appendChild(li);
      });
      slotEditorList.querySelectorAll(".slot-save-btn").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const id = Number(btn.getAttribute("data-id"));
          const row = btn.closest(".slot-editor-row");
          const name = row.querySelector(".slot-name-input").value.trim();
          const sort_order = Number(row.querySelector(".slot-order-input").value);
          const is_active = row.querySelector(".slot-active-input").checked ? 1 : 0;
          const result = await post("/api/admin/time-slots/update", { id, name, sort_order, is_active });
          setMessageOk(result.ok, "Slot saved.", "Save failed.", result.message);
          if (result.ok) {
            await loadSlotEditor();
            await loadTaskSlotOptions(true);
          }
        });
      });
    }

    async function loadPriorityEditor() {
      if (!priorityEditorList || !currentConfig?.can_manage_priorities) return;
      const res = await get("/api/admin/priorities");
      if (!res.ok) return;
      priorityEditorList.innerHTML = "";
      res.priorities.forEach((p) => {
        const li = document.createElement("li");
        li.className = "slot-editor-row priority-editor-row";
        li.innerHTML = `
          <input type="color" class="pri-color-input" data-id="${p.id}" value="${String(p.color_hex).replace(/"/g, "&quot;")}" aria-label="Color">
          <input type="text" class="pri-name-input" data-id="${p.id}" value="${String(p.name).replace(/"/g, "&quot;")}">
          <input type="number" class="pri-order-input" data-id="${p.id}" value="${p.sort_order}" style="width:4rem" title="Order">
          <label class="inline-check"><input type="checkbox" class="pri-active-input" data-id="${p.id}" ${Number(p.is_active) ? "checked" : ""}> Active</label>
          <button type="button" class="pri-save-btn" data-id="${p.id}">Save</button>
        `;
        priorityEditorList.appendChild(li);
      });
      priorityEditorList.querySelectorAll(".pri-save-btn").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const id = Number(btn.getAttribute("data-id"));
          const row = btn.closest(".priority-editor-row");
          const name = row.querySelector(".pri-name-input").value.trim();
          const sort_order = Number(row.querySelector(".pri-order-input").value);
          const is_active = row.querySelector(".pri-active-input").checked ? 1 : 0;
          const color_hex = row.querySelector(".pri-color-input").value;
          const result = await post("/api/admin/priorities/update", {
            id,
            name,
            sort_order,
            is_active,
            color_hex,
          });
          setMessageOk(result.ok, "Priority saved.", "Save failed.", result.message);
          if (result.ok) {
            await loadPriorityEditor();
            await loadTaskPriorityOptions(true);
          }
        });
      });
    }

    async function loadSystemMessagesAdmin() {
      if (!systemMessageList || !currentConfig?.can_manage_system_messages) return;
      const res = await get("/api/admin/system-messages");
      if (!res.ok) {
        setMessage(res.message || "Could not load messages.", "error");
        return;
      }
      systemMessageList.innerHTML = "";
      (res.messages || []).forEach((m) => {
        const li = document.createElement("li");
        li.className = "task-admin-list-row";
        const left = document.createElement("span");
        const typeLabel = Number(m.is_maintenance) === 1 ? "Maintenance" : "Notice";
        const active = Number(m.is_active) === 1 ? "active" : "inactive";
        const scope = m.scope === "global" ? "global" : "tenant";
        left.innerHTML = `<strong>${typeLabel}</strong> <span class="muted">(${scope} · ${active})</span><br><span>${escapeHtml(m.message_text || "")}</span>`;
        const actions = document.createElement("span");
        actions.className = "task-admin-row-actions";
        const del = document.createElement("button");
        del.type = "button";
        del.className = "ghost task-admin-delete";
        del.textContent = "Delete";
        del.addEventListener("click", async () => {
          if (!window.confirm("Delete this system message?")) return;
          const r = await post("/api/admin/system-messages/delete", { id: Number(m.id) });
          setMessageOk(r.ok, "Message deleted.", "Delete failed.", r.message);
          if (r.ok) await loadSystemMessagesAdmin();
        });
        actions.appendChild(del);
        li.appendChild(left);
        li.appendChild(actions);
        systemMessageList.appendChild(li);
      });
      if (!(res.messages || []).length) {
        const li = document.createElement("li");
        li.className = "muted";
        li.textContent = "No messages yet.";
        systemMessageList.appendChild(li);
      }
    }

    function renderPlansEditor(config) {
      if (!plansEditorWrap || !config?.levels) return;
      plansEditorWrap.innerHTML = "";
      config.levels.forEach((lvl) => {
        const wrap = document.createElement("div");
        wrap.className = "plan-edit-card card";
        wrap.style.marginBottom = "12px";
        wrap.style.padding = "12px";
        wrap.innerHTML = `
          <h4 style="margin:0 0 8px">${lvl.name} <span class="muted">(${lvl.level_key})</span></h4>
          <div class="form-grid-2">
            <div><label>Name</label><input type="text" class="pe-name" data-id="${lvl.id}" value="${String(lvl.name).replace(/"/g, "&quot;")}"></div>
            <div><label>Monthly price</label><input type="number" class="pe-price" data-id="${lvl.id}" step="0.01" min="0" value="${lvl.monthly_price != null ? lvl.monthly_price : ""}"></div>
            <div class="inline-check" style="grid-column:1/-1">
              <label class="inline-check"><input type="checkbox" class="pe-paid" data-id="${lvl.id}" ${Number(lvl.is_paid) ? "checked" : ""}> Paid</label>
              <label class="inline-check"><input type="checkbox" class="pe-ads" data-id="${lvl.id}" ${Number(lvl.show_ads) ? "checked" : ""}> Show ads</label>
              <label class="inline-check"><input type="checkbox" class="pe-act" data-id="${lvl.id}" ${Number(lvl.is_active) ? "checked" : ""}> Active</label>
            </div>
            <div class="form-grid-full"><button type="button" class="pe-save ghost" data-id="${lvl.id}">Update plan</button></div>
          </div>
        `;
        plansEditorWrap.appendChild(wrap);
      });
      plansEditorWrap.querySelectorAll(".pe-save").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const id = Number(btn.getAttribute("data-id"));
          const card = btn.closest(".plan-edit-card");
          const result = await post("/api/admin/plans/update", {
            id,
            name: card.querySelector(".pe-name").value.trim(),
            monthly_price: card.querySelector(".pe-price").value,
            is_paid: card.querySelector(".pe-paid").checked ? 1 : 0,
            show_ads: card.querySelector(".pe-ads").checked ? 1 : 0,
            is_active: card.querySelector(".pe-act").checked ? 1 : 0,
          });
          setMessageOk(result.ok, "Plan updated.", "Failed.", result.message);
          if (result.ok) await loadConfig();
        });
      });
    }

    function fillNewUserRoles() {
      if (!newUserRole) return;
      newUserRole.innerHTML = "";
      const r = currentActorRole;
      const opts = [];
      if (["owner", "super_admin"].includes(r)) {
        opts.push(
          ["tenant_user", "User"],
          ["tenant_task", "Task editor"],
          ["tenant_manager", "Manager"],
          ["tenant_admin", "Admin"],
          ["super_admin", "Super admin"],
          ["owner", "Owner"]
        );
      } else if (r === "tenant_admin") {
        opts.push(["tenant_user", "User"], ["tenant_task", "Task editor"], ["tenant_manager", "Manager"]);
      } else if (r === "tenant_manager") {
        opts.push(["tenant_user", "User"], ["tenant_task", "Task editor"]);
      }
      opts.forEach(([val, label]) => {
        const o = document.createElement("option");
        o.value = val;
        o.textContent = label;
        newUserRole.appendChild(o);
      });
    }

    function closeAllUserDetailPanels() {
      if (!userListWrap) return;
      userListWrap.querySelectorAll(".user-admin-expand").forEach((el) => el.classList.add("hidden"));
      userListWrap.querySelectorAll(".user-edit-toggle").forEach((btn) => {
        btn.textContent = "Edit";
      });
    }

    async function loadUsersAdmin() {
      if (!userListWrap) return;
      const res = await get("/api/admin/users");
      if (!res.ok) return;
      userListWrap.innerHTML = "";
      let users = res.users;
      if (currentActorRole === "tenant_manager") {
        users = users.filter((u) => u.role === "tenant_user" || u.role === "tenant_task");
      }
      users.forEach((u) => {
        const active = Number(u.is_active) === 1;
        const card = document.createElement("div");
        card.className = "user-admin-card user-admin-card-compact";

        const summary = document.createElement("div");
        summary.className = "user-admin-summary row-actions";
        const nameBlock = document.createElement("div");
        nameBlock.className = "user-admin-summary-text";
        const strong = document.createElement("strong");
        strong.textContent = u.display_name || "User";
        const roleSpan = document.createElement("span");
        roleSpan.className = "muted";
        roleSpan.textContent = ` ${u.role}`;
        nameBlock.appendChild(strong);
        nameBlock.appendChild(roleSpan);
        if (!active) {
          const badge = document.createElement("span");
          badge.className = "badge-disabled";
          badge.textContent = "Disabled";
          badge.style.marginLeft = "8px";
          nameBlock.appendChild(badge);
        }
        const editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "ghost user-edit-toggle";
        editBtn.textContent = "Edit";
        summary.appendChild(nameBlock);
        summary.appendChild(editBtn);

        const expand = document.createElement("div");
        expand.className = "user-admin-expand stack hidden";
        expand.innerHTML = `
            <label>Display name</label>
            <input type="text" class="u-name" value="${String(u.display_name).replace(/"/g, "&quot;")}">
            <label>Email</label>
            <input type="email" class="u-email" value="${u.email ? String(u.email).replace(/"/g, "&quot;") : ""}">
            <label>Role</label>
            <select class="u-role"></select>
            <div class="row-actions">
              <button type="button" class="u-save">Save</button>
              <button type="button" class="u-pin ghost">Set PIN</button>
              <button type="button" class="u-toggle ghost">${active ? "Disable" : "Enable"}</button>
            </div>
            <div class="u-pin-row hidden row-actions">
              <input type="password" class="u-pin-input" maxlength="6" placeholder="6-digit PIN" inputmode="numeric">
              <button type="button" class="u-pin-apply">Apply PIN</button>
            </div>
        `;

        const roleSel = expand.querySelector(".u-role");
        const roleChoices = [];
        if (["owner", "super_admin"].includes(currentActorRole)) {
          roleChoices.push("tenant_user", "tenant_task", "tenant_manager", "tenant_admin", "super_admin", "owner");
        } else if (currentActorRole === "tenant_admin") {
          roleChoices.push("tenant_user", "tenant_task", "tenant_manager");
        } else {
          roleChoices.push("tenant_user", "tenant_task");
        }
        roleChoices.forEach((rv) => {
          const o = document.createElement("option");
          o.value = rv;
          o.textContent = rv;
          if (rv === u.role) o.selected = true;
          roleSel.appendChild(o);
        });
        if (!roleChoices.includes(u.role)) {
          const o = document.createElement("option");
          o.value = u.role;
          o.textContent = u.role;
          o.selected = true;
          roleSel.appendChild(o);
        }

        editBtn.addEventListener("click", () => {
          const isOpen = !expand.classList.contains("hidden");
          closeAllUserDetailPanels();
          if (!isOpen) {
            expand.classList.remove("hidden");
            editBtn.textContent = "Close";
          }
        });

        expand.querySelector(".u-save").addEventListener("click", async () => {
          const result = await post("/api/admin/users/update", {
            id: u.id,
            display_name: expand.querySelector(".u-name").value.trim(),
            email: expand.querySelector(".u-email").value.trim(),
            role: expand.querySelector(".u-role").value,
          });
          setMessageOk(result.ok, "User updated.", "Update failed.", result.message);
          if (result.ok) loadUsersAdmin();
        });
        expand.querySelector(".u-pin").addEventListener("click", () => {
          expand.querySelector(".u-pin-row").classList.toggle("hidden");
        });
        expand.querySelector(".u-pin-apply").addEventListener("click", async () => {
          const pin = expand.querySelector(".u-pin-input").value;
          const result = await post("/api/admin/users/set-pin", { id: u.id, pin });
          setMessageOk(result.ok, "PIN updated.", "PIN failed.", result.message);
          expand.querySelector(".u-pin-input").value = "";
        });
        expand.querySelector(".u-toggle").addEventListener("click", async () => {
          const result = await post("/api/admin/users/update", { id: u.id, is_active: active ? 0 : 1 });
          setMessageOk(result.ok, "Updated.", "Failed.", result.message);
          if (result.ok) loadUsersAdmin();
        });

        card.appendChild(summary);
        card.appendChild(expand);
        userListWrap.appendChild(card);
      });
    }

    function updateImpersonationStatus(config) {
      if (!impersonationStatus || !config) return;
      const home = config.home_tenant_id;
      const eff = config.effective_tenant_id;
      if (config.impersonate_tenant_id) {
        impersonationStatus.textContent = `Viewing tenant #${eff} (your company is #${home}).`;
      } else {
        impersonationStatus.textContent = `Active tenant data: #${eff}.`;
      }
    }

    async function loadTenantsForImpersonation() {
      const res = await get("/api/admin/tenants");
      if (!res.ok) return;
      impersonateSelect.innerHTML = "";
      res.tenants.forEach((t) => {
        const opt = document.createElement("option");
        opt.value = String(t.id);
        opt.textContent = `${t.name} (${t.company_username})${Number(t.is_demo) ? " [demo]" : ""}`;
        impersonateSelect.appendChild(opt);
      });
    }

    async function loadConfig() {
      const me = await get("/api/auth/me");
      if (!me.ok || !me.company_authenticated) {
        window.location.href = "/login";
        return;
      }
      if (!me.user) {
        window.location.href = "/dashboard";
        return;
      }
      if (!isTaskEditorRole(me.user.role)) {
        window.location.href = "/dashboard";
        return;
      }

      currentActorRole = me.user.role;
      if (me.resolved_theme) {
        applyResolvedTheme(me.resolved_theme);
      }
      updateCompanyTimezoneFromMe(me);
      refreshFooterBar();

      const config = await get("/api/admin/config");
      if (!config.ok) {
        setMessage(config.message || "Unable to load admin configuration.", "error");
        return;
      }

      currentConfig = config;
      const globalAdmin = ["owner", "super_admin"].includes(currentActorRole);
      if (systemMessageScopeRow) systemMessageScopeRow.classList.toggle("hidden", !globalAdmin);
      if (systemMessageMaintenanceRow) systemMessageMaintenanceRow.classList.toggle("hidden", !globalAdmin);
      if (systemMessageMaintenanceHint) {
        systemMessageMaintenanceHint.textContent = globalAdmin
          ? "You can publish tenant messages, global messages, and global maintenance mode."
          : "Notices you publish are visible to everyone signed in at your company.";
      }
      if (systemMessageMaintenance) {
        systemMessageMaintenance.checked = false;
        systemMessageMaintenance.disabled = false;
      }
      if (systemMessageGlobal) {
        systemMessageGlobal.checked = false;
        systemMessageGlobal.disabled = false;
      }
      buildTabs(config, currentActorRole);

      const tn = config.tenant;
      if (tn && companyProfileForm) {
        const el = (id) => document.getElementById(id);
        if (el("co-name")) el("co-name").value = tn.name || "";
        if (el("co-logo-url")) el("co-logo-url").value = tn.logo_url || "";
        if (el("co-owner-email")) el("co-owner-email").value = tn.owner_email || "";
        if (el("co-timezone")) el("co-timezone").value = tn.timezone || "UTC";
        if (el("co-zip-code")) el("co-zip-code").value = tn.zip_code != null ? String(tn.zip_code) : "";
        if (el("co-theme")) el("co-theme").value = tn.theme_default === "dark" ? "dark" : "light";
        if (el("co-password")) el("co-password").value = "";
        if (el("co-company-username")) el("co-company-username").value = tn.company_username || "";
        if (el("co-is-active")) el("co-is-active").checked = Number(tn.is_active) === 1;
        const pk = el("co-plan-key");
        if (pk) {
          pk.innerHTML = "";
          let planMatched = false;
          (config.levels || []).forEach((l) => {
            const o = document.createElement("option");
            o.value = l.level_key;
            o.textContent = `${l.name} (${l.level_key})`;
            if (l.level_key === tn.plan_key) {
              o.selected = true;
              planMatched = true;
            }
            pk.appendChild(o);
          });
          if (!planMatched && tn.plan_key) {
            const o = document.createElement("option");
            o.value = tn.plan_key;
            o.textContent = `${tn.plan_key} (current)`;
            o.selected = true;
            pk.appendChild(o);
          }
        }
        if (coPlatformFields) {
          coPlatformFields.classList.toggle("hidden", !config.can_manage_platform_tenant);
        }
      }

      if (config.can_impersonate && impersonationCard) {
        impersonationCard.classList.remove("hidden");
        await loadTenantsForImpersonation();
        if (impersonateSelect && config.effective_tenant_id) {
          impersonateSelect.value = String(config.effective_tenant_id);
        }
        updateImpersonationStatus(config);
      }

      await loadTaskSlotOptions(true);
      await loadTaskPriorityOptions(true);
      await loadTenantUsers();
      await loadTasks();
      if (config.can_manage_time_slots) await loadSlotEditor();
      if (config.can_manage_priorities) await loadPriorityEditor();
      if (config.can_manage_system_messages) await loadSystemMessagesAdmin();
      if (config.can_manage_plans) renderPlansEditor(config);
      fillNewUserRoles();
      await loadUsersAdmin();
      buildMatrix(config);
    }

    taskRecurrence?.addEventListener("change", () => {
      if ((taskRecurrence.value === "biweekly" || taskRecurrence.value === "quarterly") && taskAnchorDate && !taskAnchorDate.value) {
        taskAnchorDate.value = new Date().toISOString().slice(0, 10);
      }
      updateRecurrenceFields();
    });
    taskEl("inline-", "task-recurrence")?.addEventListener("change", () => {
      const iad = taskEl("inline-", "task-anchor-date");
      const type = taskEl("inline-", "task-recurrence")?.value;
      if ((type === "biweekly" || type === "quarterly") && iad && !iad.value) {
        iad.value = new Date().toISOString().slice(0, 10);
      }
      updateRecurrenceFieldsFor("inline-");
    });
    taskNewBtn?.addEventListener("click", () => {
      hideTaskInlineEditor();
      resetTaskForm();
    });

    taskForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const payload = buildTaskPayload("");
      const id = taskEditId.value.trim();
      const result = id
        ? await post("/api/admin/tasks/update", { id: Number(id), ...payload })
        : await post("/api/admin/tasks/create", payload);
      setMessageOk(result.ok, "Task saved.", "Save failed.", result.message);
      if (result.ok) {
        hideTaskInlineEditor();
        resetTaskForm();
        await loadTasks();
      }
    });

    taskInlineForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = taskEl("inline-", "task-edit-id")?.value?.trim();
      if (!id) return;
      const payload = buildTaskPayload("inline-");
      const result = await post("/api/admin/tasks/update", { id: Number(id), ...payload });
      setMessageOk(result.ok, "Task saved.", "Save failed.", result.message);
      if (result.ok) {
        hideTaskInlineEditor();
        await loadTasks();
      }
    });
    document.getElementById("inline-task-cancel")?.addEventListener("click", () => {
      hideTaskInlineEditor();
    });

    newSlotAdd?.addEventListener("click", async () => {
      const name = newSlotName.value.trim();
      if (!name) return;
      const result = await post("/api/admin/time-slots/create", { name });
      setMessageOk(result.ok, "Slot added.", "Failed.", result.message);
      if (result.ok) {
        newSlotName.value = "";
        await loadSlotEditor();
        await loadTaskSlotOptions(true);
      }
    });

    newPriorityAdd?.addEventListener("click", async () => {
      const name = newPriorityName.value.trim();
      if (!name) return;
      const color_hex = newPriorityColor?.value || "#64748b";
      const result = await post("/api/admin/priorities/create", { name, color_hex });
      setMessageOk(result.ok, "Priority added.", "Failed.", result.message);
      if (result.ok) {
        newPriorityName.value = "";
        await loadPriorityEditor();
        await loadTaskPriorityOptions(true);
      }
    });

    companyProfileForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const payload = {
        name: document.getElementById("co-name")?.value?.trim() || "",
        owner_email: document.getElementById("co-owner-email")?.value?.trim() || "",
        timezone: document.getElementById("co-timezone")?.value?.trim() || "UTC",
        zip_code: document.getElementById("co-zip-code")?.value?.trim() || "",
        theme_default: document.getElementById("co-theme")?.value || "light",
        logo_url: document.getElementById("co-logo-url")?.value?.trim() || "",
      };
      const pw = document.getElementById("co-password")?.value || "";
      if (pw !== "") payload.company_password = pw;
      if (currentConfig?.can_manage_platform_tenant) {
        const cu = document.getElementById("co-company-username")?.value?.trim().toLowerCase() || "";
        if (cu !== "") payload.company_username = cu;
        payload.plan_key = document.getElementById("co-plan-key")?.value || "";
        payload.is_active = document.getElementById("co-is-active")?.checked ? 1 : 0;
      }
      const result = await post("/api/admin/tenant-profile", payload);
      setMessageOk(result.ok, "Company settings saved.", "Failed.", result.message);
      if (result.ok) {
        const m = await get("/api/auth/me");
        if (m.ok && m.resolved_theme) applyResolvedTheme(m.resolved_theme);
        await loadConfig();
      }
    });

    planCreateForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const result = await post("/api/admin/plans/create", {
        level_key: document.getElementById("plan-level-key")?.value?.trim().toLowerCase() || "",
        name: document.getElementById("plan-name")?.value?.trim() || "",
        monthly_price: document.getElementById("plan-price")?.value || null,
        is_paid: document.getElementById("plan-is-paid")?.checked ? 1 : 0,
        show_ads: document.getElementById("plan-show-ads")?.checked ? 1 : 0,
      });
      setMessageOk(
        result.ok,
        "Plan created. Configure features on the Feature matrix tab.",
        "Failed.",
        result.message
      );
      if (result.ok) {
        planCreateForm.reset();
        const ads = document.getElementById("plan-show-ads");
        if (ads) ads.checked = true;
        await loadConfig();
      }
    });

    userCreateForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const result = await post("/api/admin/users/create", {
        display_name: newUserName.value.trim(),
        email: newUserEmail.value.trim(),
        role: newUserRole.value,
        pin: newUserPin.value,
      });
      setMessageOk(result.ok, "User created.", "Create failed.", result.message);
      if (result.ok) {
        newUserName.value = "";
        newUserEmail.value = "";
        newUserPin.value = "";
        await loadUsersAdmin();
        await loadTenantUsers();
        if (trainingAdminLoaded) await loadTrainingAdminPanel();
      }
    });

    systemMessageForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const text = (systemMessageText?.value || "").trim();
      if (!text) return;
      const globalAdmin = ["owner", "super_admin"].includes(currentActorRole);
      const maintenance =
        globalAdmin && systemMessageMaintenance?.checked ? 1 : 0;
      const scope =
        maintenance === 1
          ? "global"
          : globalAdmin && systemMessageGlobal?.checked
            ? "global"
            : "tenant";
      const result = await post("/api/admin/system-messages/create", {
        message_text: text,
        is_maintenance: maintenance,
        scope,
      });
      setMessageOk(result.ok, "Message published.", "Publish failed.", result.message);
      if (result.ok) {
        if (systemMessageText) systemMessageText.value = "";
        if (systemMessageMaintenance) systemMessageMaintenance.checked = false;
        if (systemMessageGlobal) systemMessageGlobal.checked = false;
        await loadSystemMessagesAdmin();
      }
    });

    systemMessageMaintenance?.addEventListener("change", () => {
      if (!systemMessageGlobal) return;
      if (systemMessageMaintenance.checked) {
        systemMessageGlobal.checked = true;
        systemMessageGlobal.disabled = true;
      } else {
        systemMessageGlobal.disabled = false;
      }
    });

    impersonateGo?.addEventListener("click", async () => {
      const tenantId = Number(impersonateSelect.value || 0);
      const result = await post("/api/admin/impersonate", { tenant_id: tenantId });
      setMessageOk(result.ok, "Now viewing selected tenant.", "Impersonation failed.", result.message);
      if (result.ok) await loadConfig();
    });

    impersonateClear?.addEventListener("click", async () => {
      const result = await post("/api/admin/impersonate-clear");
      setMessageOk(result.ok, "Stopped viewing other tenant.", "Clear failed.", result.message);
      if (result.ok) await loadConfig();
    });

    createTenantForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const result = await post("/api/admin/tenants/create", {
        company_name: document.getElementById("ct-name")?.value?.trim() || "",
        company_username: document.getElementById("ct-username")?.value?.trim().toLowerCase() || "",
        company_password: document.getElementById("ct-password")?.value || "",
        owner_email: document.getElementById("ct-email")?.value?.trim() || "",
        admin_display_name: document.getElementById("ct-admin-name")?.value?.trim() || "",
        admin_pin: document.getElementById("ct-admin-pin")?.value || "",
        timezone: document.getElementById("ct-tz")?.value?.trim() || "UTC",
      });
      setMessageOk(
        result.ok,
        `Company created (#${result.tenant_id}). They can sign in with the new company login.`,
        "Create failed.",
        result.message
      );
      if (result.ok) {
        createTenantForm.reset();
        const tz = document.getElementById("ct-tz");
        if (tz) tz.value = "UTC";
        await loadTenantsForImpersonation();
      }
    });

    adminThemeToggle?.addEventListener("click", async () => {
      const next = document.body.classList.contains("theme-dark") ? "light" : "dark";
      const r = await post("/api/auth/theme-preference", { theme: next });
      if (r.ok && r.resolved_theme) {
        applyResolvedTheme(r.resolved_theme);
      } else {
        applyResolvedTheme(next);
      }
    });

    saveMatrixBtn?.addEventListener("click", async () => {
      if (!currentConfig?.can_manage_matrix) return;
      const inputs = matrixWrap.querySelectorAll("input[type='checkbox']");
      const entries = Array.from(inputs).map((input) => ({
        level_id: Number(input.getAttribute("data-level-id") || 0),
        feature_id: Number(input.getAttribute("data-feature-id") || 0),
        is_enabled: input.checked ? 1 : 0,
      }));
      const result = await post("/api/admin/feature-matrix", { entries });
      setMessageOk(result.ok, "Matrix saved.", "Failed.", result.message);
    });

    function resetTrainingDefForm() {
      document.getElementById("tr-def-id").value = "";
      document.getElementById("tr-def-title").value = "";
      document.getElementById("tr-def-details").value = "";
      document.getElementById("tr-def-sort").value = "0";
      document.getElementById("tr-def-active").checked = true;
    }
    function resetTrainingTplForm() {
      document.getElementById("tr-tpl-id").value = "";
      document.getElementById("tr-tpl-name").value = "";
      document.getElementById("tr-tpl-desc").value = "";
      document.getElementById("tr-tpl-active").checked = true;
    }

    document.getElementById("tr-def-new")?.addEventListener("click", () => resetTrainingDefForm());
    document.getElementById("tr-tpl-new")?.addEventListener("click", () => resetTrainingTplForm());

    document.getElementById("tr-def-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = Number(document.getElementById("tr-def-id").value || 0);
      const result = await post("/api/training/definitions/save", {
        id,
        title: document.getElementById("tr-def-title").value.trim(),
        details: document.getElementById("tr-def-details").value.trim(),
        sort_order: Number(document.getElementById("tr-def-sort").value || 0),
        is_active: document.getElementById("tr-def-active").checked ? 1 : 0,
      });
      setMessageOk(result.ok, "Training item saved.", "Save failed.", result.message);
      if (result.ok) {
        resetTrainingDefForm();
        await loadTrainingAdminPanel();
      }
    });

    document.getElementById("tr-tpl-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = Number(document.getElementById("tr-tpl-id").value || 0);
      const result = await post("/api/training/templates/save", {
        id,
        name: document.getElementById("tr-tpl-name").value.trim(),
        description: document.getElementById("tr-tpl-desc").value.trim(),
        is_active: document.getElementById("tr-tpl-active").checked ? 1 : 0,
      });
      setMessageOk(result.ok, "Template saved.", "Save failed.", result.message);
      if (result.ok) {
        resetTrainingTplForm();
        await loadTrainingAdminPanel();
      }
    });

    document.getElementById("tr-items-template")?.addEventListener("change", () => {
      syncTemplateBuilderFromCache();
      renderTemplateOrderList();
    });

    document.getElementById("tr-item-add")?.addEventListener("click", () => {
      const pick = document.getElementById("tr-item-pick-def");
      const id = Number(pick?.value || 0);
      if (id < 1) return;
      if (trTemplateItemOrder.includes(id)) {
        setMessage("That item is already in the list.", "error");
        return;
      }
      setMessage("");
      trTemplateItemOrder.push(id);
      renderTemplateOrderList();
    });

    document.getElementById("tr-items-save")?.addEventListener("click", async () => {
      const templateId = Number(document.getElementById("tr-items-template").value || 0);
      const result = await post("/api/training/templates/items", {
        template_id: templateId,
        definition_ids: trTemplateItemOrder,
      });
      setMessageOk(result.ok, "Template items updated.", "Failed.", result.message);
      if (result.ok) await loadTrainingAdminPanel();
    });

    document.getElementById("tr-assign-one")?.addEventListener("click", async () => {
      const user_id = Number(document.getElementById("tr-assign-user").value || 0);
      const definition_id = Number(document.getElementById("tr-assign-def").value || 0);
      const result = await post("/api/training/assign-definition", { user_id, definition_id });
      setMessageOk(result.ok, "Assigned.", "Assign failed.", result.message);
    });

    document.getElementById("tr-assign-bundle")?.addEventListener("click", async () => {
      const user_id = Number(document.getElementById("tr-assign-user").value || 0);
      const template_id = Number(document.getElementById("tr-assign-tpl").value || 0);
      const result = await post("/api/training/assign-template", { user_id, template_id });
      setMessageOk(
        result.ok,
        `Created ${result.assignments_created ?? 0} assignment(s).`,
        "Assign failed.",
        result.message
      );
    });

    updateRecurrenceFields();
    loadConfig();
  }

  function setupReports() {
    if (path !== "/reports") return;

    const errEl = document.getElementById("reports-error");
    const tbodyDay = document.querySelector("#reports-by-day tbody");
    const tbodyUser = document.querySelector("#reports-by-user tbody");
    const summaryEl = document.getElementById("reports-summary");
    const startIn = document.getElementById("reports-start");
    const endIn = document.getElementById("reports-end");

    function localYMD(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const day = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${day}`;
    }

    function ymdInTimeZone(date, timeZone) {
      try {
        const parts = new Intl.DateTimeFormat("en-US", {
          timeZone,
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
        }).formatToParts(date);
        const y = parts.find((p) => p.type === "year")?.value;
        const m = parts.find((p) => p.type === "month")?.value;
        const d = parts.find((p) => p.type === "day")?.value;
        if (y && m && d) return `${y}-${m}-${d}`;
      } catch {
        /* fall through */
      }
      return null;
    }

    function defaultRange() {
      const tz = window.__COMPANY_TZ__;
      if (tz) {
        const now = new Date();
        const end = ymdInTimeZone(now, tz);
        const past = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
        const start = ymdInTimeZone(past, tz);
        if (end && start) return { start, end };
      }
      const end = new Date();
      const start = new Date();
      start.setDate(start.getDate() - 30);
      return { start: localYMD(start), end: localYMD(end) };
    }

    function renderTables(data) {
      if (errEl) {
        errEl.textContent = "";
        errEl.classList.add("hidden");
      }
      const periodEl = document.getElementById("reports-period");
      if (periodEl && data.start_date && data.end_date) {
        periodEl.textContent = `From ${data.start_date} through ${data.end_date}.`;
      }
      if (summaryEl) {
        summaryEl.innerHTML = `<div>Open today: <strong>${data.open_today}</strong></div><div>Completed in range: <strong>${data.completed_total}</strong></div>`;
      }
      if (tbodyDay) {
        tbodyDay.innerHTML = "";
        (data.completed_by_day || []).forEach((row) => {
          const tr = document.createElement("tr");
          const td1 = document.createElement("td");
          td1.textContent = row.date;
          const td2 = document.createElement("td");
          td2.textContent = String(row.count);
          tr.appendChild(td1);
          tr.appendChild(td2);
          tbodyDay.appendChild(tr);
        });
        if (!data.completed_by_day?.length) {
          const tr = document.createElement("tr");
          const td = document.createElement("td");
          td.colSpan = 2;
          td.className = "muted";
          td.textContent = "No completions in this period.";
          tr.appendChild(td);
          tbodyDay.appendChild(tr);
        }
      }
      if (tbodyUser) {
        tbodyUser.innerHTML = "";
        (data.completed_by_user || []).forEach((row) => {
          const tr = document.createElement("tr");
          const td1 = document.createElement("td");
          td1.textContent = row.display_name;
          const td2 = document.createElement("td");
          td2.textContent = String(row.count);
          tr.appendChild(td1);
          tr.appendChild(td2);
          tbodyUser.appendChild(tr);
        });
        if (!data.completed_by_user?.length) {
          const tr = document.createElement("tr");
          const td = document.createElement("td");
          td.colSpan = 2;
          td.className = "muted";
          td.textContent = "No data.";
          tr.appendChild(td);
          tbodyUser.appendChild(tr);
        }
      }
      const tbodyDetail = document.querySelector("#reports-detail tbody");
      if (tbodyDetail) {
        tbodyDetail.innerHTML = "";
        (data.completed_tasks_detail || []).forEach((row) => {
          const tr = document.createElement("tr");
          const c0 = document.createElement("td");
          c0.textContent = row.completed_at != null ? formatCompanyDateTime(row.completed_at) : "";
          const c1 = document.createElement("td");
          c1.textContent = row.title != null ? String(row.title) : "";
          const c2 = document.createElement("td");
          c2.textContent = row.completed_by != null ? String(row.completed_by) : "";
          const c3 = document.createElement("td");
          c3.className = "reports-detail-cell muted";
          const det = row.details != null ? String(row.details).trim() : "";
          c3.textContent = det.length > 240 ? `${det.slice(0, 240)}…` : det || "—";
          tr.appendChild(c0);
          tr.appendChild(c1);
          tr.appendChild(c2);
          tr.appendChild(c3);
          tbodyDetail.appendChild(tr);
        });
        if (!(data.completed_tasks_detail || []).length) {
          const tr = document.createElement("tr");
          const td = document.createElement("td");
          td.colSpan = 4;
          td.className = "muted";
          td.textContent = "No completions in this period.";
          tr.appendChild(td);
          tbodyDetail.appendChild(tr);
        }
      }
    }

    async function fetchRange(start, end) {
      const q = `start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;
      const data = await get(`/api/reports/summary?${q}`);
      if (!data.ok) {
        if (errEl) {
          errEl.textContent = data.message || "Unable to load reports.";
          errEl.classList.remove("hidden");
        }
        return;
      }
      renderTables(data);
    }

    async function init() {
      const me = await get("/api/auth/me");
      if (!me.ok || !me.company_authenticated) {
        window.location.href = "/login";
        return;
      }
      if (!me.user) {
        window.location.href = "/dashboard";
        return;
      }
      if (me.resolved_theme) applyResolvedTheme(me.resolved_theme);
      updateCompanyTimezoneFromMe(me);
      refreshFooterBar();
      if (!me.integrations?.features?.tasks_reports) {
        if (errEl) {
          errEl.textContent = "Reports are not enabled for your subscription plan.";
          errEl.classList.remove("hidden");
        }
        return;
      }
      if (!me.can_view_reports) {
        window.location.href = "/dashboard";
        return;
      }

      const dr = defaultRange();
      if (startIn && !startIn.value) startIn.value = dr.start;
      if (endIn && !endIn.value) endIn.value = dr.end;
      await fetchRange(startIn.value, endIn.value);
    }

    document.getElementById("reports-range-form")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const s = startIn?.value?.trim();
      const en = endIn?.value?.trim();
      if (!s || !en) return;
      await fetchRange(s, en);
    });

    init();

    document.getElementById("theme-toggle-reports")?.addEventListener("click", async () => {
      const next = document.body.classList.contains("theme-dark") ? "light" : "dark";
      const r = await post("/api/auth/theme-preference", { theme: next });
      if (r.ok && r.resolved_theme) applyResolvedTheme(r.resolved_theme);
      else applyResolvedTheme(next);
    });

    document.getElementById("logout-all-reports")?.addEventListener("click", async () => {
      await post("/api/auth/logout-all");
      window.location.href = "/login";
    });
  }

  function setupTraining() {
    if (path !== "/training") return;

    const errEl = document.getElementById("training-error");
    const disabledEl = document.getElementById("training-disabled");
    const wrap = document.getElementById("training-enabled-wrap");
    const myList = document.getElementById("training-my-list");
    const teamWrap = document.getElementById("training-team-wrap");
    const teamAccordion = document.getElementById("training-team-accordion");
    const adminLink = document.getElementById("training-admin-link");
    const managerRoles = ["owner", "super_admin", "tenant_admin", "tenant_manager"];

    async function load() {
      const me = await get("/api/auth/me");
      if (!me.ok || !me.company_authenticated) {
        window.location.href = "/login";
        return;
      }
      if (!me.user) {
        window.location.href = "/dashboard";
        return;
      }
      if (me.resolved_theme) applyResolvedTheme(me.resolved_theme);
      updateCompanyTimezoneFromMe(me);
      refreshFooterBar();
      if (managerRoles.includes(me.user.role)) {
        adminLink?.classList.remove("hidden");
      }
      if (!me.integrations?.features?.training_employee) {
        disabledEl?.classList.remove("hidden");
        wrap?.classList.add("hidden");
        teamWrap?.classList.add("hidden");
        return;
      }
      disabledEl?.classList.add("hidden");
      wrap?.classList.remove("hidden");

      const mine = await get("/api/training/my-assignments");
      if (!mine.ok) {
        if (errEl) {
          errEl.textContent = mine.message || "Failed to load training.";
          errEl.classList.remove("hidden");
        }
        return;
      }
      errEl?.classList.add("hidden");

      if (myList) {
        myList.innerHTML = "";
        (mine.assignments || []).forEach((a) => {
          const li = document.createElement("li");
          const fullyDone =
            a.signoff_see_at && a.signoff_do_at && a.signoff_teach_at;
          const sub = document.createElement("div");
          const h = document.createElement("strong");
          h.textContent = a.title || "Training";
          sub.appendChild(h);
          if (a.details) {
            const p = document.createElement("p");
            p.className = "muted";
            p.textContent = a.details;
            sub.appendChild(p);
          }
          function addSignoffLine(label, at, byName) {
            const p = document.createElement("p");
            p.className = "muted";
            p.style.margin = "4px 0 0 0";
            p.textContent = at
              ? `${label}: signed${byName ? ` (${byName})` : ""}`
              : `${label}: pending manager sign-off`;
            sub.appendChild(p);
          }
          if (fullyDone) {
            const st = document.createElement("p");
            st.className = "muted";
            st.textContent = `All sign-offs complete${a.completed_by_name ? ` (recorded by ${a.completed_by_name})` : ""}.`;
            sub.appendChild(st);
          } else {
            addSignoffLine("See 1", a.signoff_see_at, a.signoff_see_by_name);
            addSignoffLine("Do 1", a.signoff_do_at, a.signoff_do_by_name);
            addSignoffLine("Teach 1", a.signoff_teach_at, a.signoff_teach_by_name);
          }
          li.appendChild(sub);
          myList.appendChild(li);
        });
        if (!(mine.assignments || []).length) {
          const li = document.createElement("li");
          li.className = "muted";
          li.textContent = "No training assigned.";
          myList.appendChild(li);
        }
      }

      if (managerRoles.includes(me.user.role)) {
        const team = await get("/api/training/team-assignments");
        if (team.ok && teamWrap && teamAccordion) {
          teamWrap.classList.remove("hidden");
          teamAccordion.innerHTML = "";

          function missingSignoffSteps(a) {
            let n = 0;
            if (!a.signoff_see_at) n++;
            if (!a.signoff_do_at) n++;
            if (!a.signoff_teach_at) n++;
            return n;
          }

          function appendSignoffCell(row, a, kind, label) {
            const cell = document.createElement("div");
            cell.className = "training-signoff-cell";
            const lab = document.createElement("div");
            lab.className = "training-signoff-label";
            lab.textContent = label;
            cell.appendChild(lab);
            const atKey = `signoff_${kind}_at`;
            const nameKey = `signoff_${kind}_by_name`;
            if (a[atKey]) {
              const done = document.createElement("div");
              done.className = "training-signoff-done muted";
              done.textContent = `✓ ${a[nameKey] || ""}`.trim();
              cell.appendChild(done);
            } else {
              const btn = document.createElement("button");
              btn.type = "button";
              btn.className = "ghost training-signoff-btn";
              btn.textContent = "Sign off";
              btn.addEventListener("click", async () => {
                const r = await post("/api/training/signoff", {
                  assignment_id: a.id,
                  kind,
                });
                if (!r.ok) {
                  if (errEl) {
                    errEl.textContent = r.message || "Could not record sign-off.";
                    errEl.classList.remove("hidden");
                  }
                  return;
                }
                errEl?.classList.add("hidden");
                await load();
              });
              cell.appendChild(btn);
            }
            row.appendChild(cell);
          }

          const rows = team.assignments || [];
          if (!rows.length) {
            const empty = document.createElement("p");
            empty.className = "muted";
            empty.textContent = "No team assignments.";
            teamAccordion.appendChild(empty);
          } else {
            const byUser = new Map();
            rows.forEach((a) => {
              const uid = String(a.user_id);
              if (!byUser.has(uid)) {
                byUser.set(uid, { user_name: a.user_name || "User", items: [] });
              }
              byUser.get(uid).items.push(a);
            });

            const userKeys = Array.from(byUser.keys()).sort((ka, kb) => {
              const ga = byUser.get(ka);
              const gb = byUser.get(kb);
              const pa = ga.items.reduce((s, x) => s + missingSignoffSteps(x), 0);
              const pb = gb.items.reduce((s, x) => s + missingSignoffSteps(x), 0);
              if (pb !== pa) return pb - pa;
              return (ga.user_name || "").localeCompare(gb.user_name || "", undefined, {
                sensitivity: "base",
              });
            });

            userKeys.forEach((uid) => {
              const { user_name: userName, items } = byUser.get(uid);
              const totalMissing = items.reduce((s, a) => s + missingSignoffSteps(a), 0);
              const details = document.createElement("details");
              details.className = "training-user-block";
              if (totalMissing > 0) {
                details.open = true;
              }
              const summary = document.createElement("summary");
              summary.className = "training-user-summary";
              const nameEl = document.createElement("span");
              nameEl.className = "training-user-name";
              nameEl.textContent = userName;
              const meta = document.createElement("span");
              meta.className = "training-user-meta muted";
              if (totalMissing > 0) {
                meta.textContent = `${items.length} training${items.length === 1 ? "" : "s"} · ${totalMissing} sign-off${totalMissing === 1 ? "" : "s"} pending`;
              } else {
                meta.textContent = `${items.length} training${items.length === 1 ? "" : "s"} · all signed`;
              }
              summary.appendChild(nameEl);
              summary.appendChild(meta);
              details.appendChild(summary);

              const body = document.createElement("div");
              body.className = "training-user-body";
              items.forEach((a) => {
                const card = document.createElement("div");
                card.className = "training-assignment-card";
                const head = document.createElement("div");
                head.className = "training-assignment-head";
                const titleEl = document.createElement("div");
                titleEl.className = "training-assignment-title";
                titleEl.textContent = a.title || "Training";
                head.appendChild(titleEl);
                if (a.assigned_at != null) {
                  const ad = document.createElement("div");
                  ad.className = "muted training-assignment-assigned";
                  ad.textContent = `Assigned ${String(a.assigned_at)}`;
                  head.appendChild(ad);
                }
                card.appendChild(head);
                if (a.details) {
                  const det = document.createElement("p");
                  det.className = "muted training-assignment-details";
                  det.textContent = String(a.details);
                  card.appendChild(det);
                }
                const signRow = document.createElement("div");
                signRow.className = "training-signoff-row";
                appendSignoffCell(signRow, a, "see", "See 1");
                appendSignoffCell(signRow, a, "do", "Do 1");
                appendSignoffCell(signRow, a, "teach", "Teach 1");
                card.appendChild(signRow);
                body.appendChild(card);
              });
              details.appendChild(body);
              teamAccordion.appendChild(details);
            });
          }
        }
      } else if (teamWrap) {
        teamWrap.classList.add("hidden");
      }
    }

    load();

    document.getElementById("theme-toggle-training")?.addEventListener("click", async () => {
      const next = document.body.classList.contains("theme-dark") ? "light" : "dark";
      const r = await post("/api/auth/theme-preference", { theme: next });
      if (r.ok && r.resolved_theme) applyResolvedTheme(r.resolved_theme);
      else applyResolvedTheme(next);
    });

    document.getElementById("logout-all-training")?.addEventListener("click", async () => {
      await post("/api/auth/logout-all");
      window.location.href = "/login";
    });
  }

  function setupHelp() {
    if (path !== "/help" && path !== "/changelog") return;

    const toggleId = path === "/help" ? "theme-toggle-help" : "theme-toggle-changelog";
    document.getElementById(toggleId)?.addEventListener("click", async () => {
      const next = document.body.classList.contains("theme-dark") ? "light" : "dark";
      const r = await post("/api/auth/theme-preference", { theme: next });
      if (r.ok && r.resolved_theme) applyResolvedTheme(r.resolved_theme);
      else applyResolvedTheme(next);
    });

    (async () => {
      const me = await get("/api/auth/me");
      if (me.ok && me.resolved_theme) applyResolvedTheme(me.resolved_theme);
      if (me.ok) {
        updateCompanyTimezoneFromMe(me);
        refreshFooterBar();
      }
    })();
  }

  setupLogin();
  setupRegister();
  setupDashboard();
  setupAdmin();
  setupReports();
  setupTraining();
  setupHelp();

  refreshFooterBar();
})();
