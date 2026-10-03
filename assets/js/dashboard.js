(function () {
  const data = window.__DASHBOARD__ || {
    monthly: { month: "", days: [] },
    quarterly: { year: 2026, quarters: [] },
    heatmap: { weeks: [], month_labels: [], summary: {} },
    distribution: [],
    goalTarget: 4,
    weeklyGoalTarget: null,
  };

  const rootStyle = getComputedStyle(document.documentElement);
  const cssVar = (name) => rootStyle.getPropertyValue(name).trim();
  const gridColor = cssVar("--overlay-2") || "rgba(255,255,255,.06)";
  const amber = cssVar("--amber") || "#e8a33d";
  const teal = cssVar("--teal") || "#4fa69c";

  Chart.defaults.color = cssVar("--muted") || "#8592a6";
  Chart.defaults.font.family =
    "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif";
  Chart.defaults.font.size = 12;

  let monthlyChart;
  let quarterlyChart;
  let selectedMonth = data.monthly?.month || new Date().toISOString().slice(0, 7);
  let selectedQuarterYear = Number(data.quarterly?.year) || new Date().getFullYear();
  let selectedHeatmapYear = data.heatmap?.year ? String(data.heatmap.year) : "";
  let currentMonthlyDays = data.monthly?.days || [];
  let currentQuarterlyData = data.quarterly || null;
  let currentHeatmapData = data.heatmap || null;
  let activeStudyTab = "monthly";
  let selectedQuarterIndex = null;

  function fmtMinutes(total) {
    total = Number(total) || 0;
    const h = Math.floor(total / 60),
      m = total % 60;
    if (h === 0) return m + "m";
    if (m === 0) return h + "h";
    return `${h}h ${m}m`;
  }

  function monthLabel(m) {
    if (!m) return "";
    const [y, mo] = m.split("-").map(Number);
    return new Date(y, mo - 1, 1).toLocaleDateString(undefined, {
      month: "long",
      year: "numeric",
    });
  }

  function updateStudyHint() {
    const hint =
      document.getElementById("study-panel-hint") ||
      document.getElementById("monthly-hint");
    if (!hint) return;
    if (activeStudyTab === "monthly") {
      hint.textContent = monthLabel(selectedMonth) + " · cumulative hours";
    } else {
      hint.textContent = selectedQuarterYear + " · quarterly breakdown & hours";
    }
  }

  function renderDayBreakdown(date, rows) {
    const wrap = document.getElementById("day-breakdown");
    if (!wrap) return;
    wrap.hidden = false;
    if (!rows.length) {
      wrap.innerHTML = `<div class="day-breakdown-head"><strong>${date}</strong><span>No study logged</span></div>`;
      return;
    }
    wrap.innerHTML = `
      <div class="day-breakdown-head">
        <strong>${date}</strong>
        <span>${rows.reduce((sum, row) => sum + Number(row.total_minutes || 0), 0)} minutes total</span>
      </div>
      <div class="day-breakdown-list">
        ${rows
          .map(
            (row) => `
          <div class="day-breakdown-row">
            <span class="dot" style="background:${row.color_hex};"></span>
            <div class="day-breakdown-label">
              <strong>${escapeHtml(row.subject_name)}</strong>
              <span>${escapeHtml(row.material_title)} · ${row.total_cycles} cycles</span>
            </div>
            <span class="day-breakdown-time num">${fmtMinutes(row.total_minutes)}</span>
          </div>
        `,
          )
          .join("")}
      </div>`;
  }

  async function loadDayBreakdown(date) {
    const wrap = document.getElementById("day-breakdown");
    if (wrap) {
      wrap.hidden = false;
      wrap.innerHTML =
        '<div class="day-breakdown-loading">Loading day details...</div>';
    }
    try {
      const result = await api("api/stats.php?day=" + encodeURIComponent(date));
      renderDayBreakdown(result.date, result.breakdown || []);
    } catch (error) {
      if (wrap)
        wrap.innerHTML =
          '<div class="day-breakdown-loading">Could not load day details.</div>';
    }
  }

  function renderMonthly(days) {
    const ctx = document.getElementById("chart-monthly");
    if (!ctx || !days || !days.length) return;
    currentMonthlyDays = days;

    const labels = days.map((d) => String(d.day_num));
    const daily = days.map((d) => +(Number(d.total_minutes) / 60).toFixed(2));
    const cumulative = days.map(
      (d) => +(Number(d.cumulative_minutes) / 60).toFixed(2),
    );

    if (monthlyChart) {
      monthlyChart.data.labels = labels;
      monthlyChart.data.datasets[0].data = daily;
      monthlyChart.data.datasets[1].data = cumulative;
      monthlyChart.update();
      return;
    }

    const c2d = ctx.getContext("2d");
    const tealGrad = c2d.createLinearGradient(0, 0, 0, 180);
    tealGrad.addColorStop(0, "rgba(16, 185, 129, 0.28)");
    tealGrad.addColorStop(1, "rgba(16, 185, 129, 0.00)");

    monthlyChart = new Chart(ctx, {
      data: {
        labels,
        datasets: [
          {
            type: "bar",
            label: "Study + revision that day",
            data: daily,
            backgroundColor: amber,
            hoverBackgroundColor: "#fbbf24",
            borderRadius: 6,
            maxBarThickness: 16,
            yAxisID: "y",
            order: 2,
          },
          {
            type: "line",
            label: "Cumulative this month",
            data: cumulative,
            borderColor: teal,
            backgroundColor: tealGrad,
            borderWidth: 2.5,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: teal,
            pointHoverBorderColor: "#ffffff",
            pointHoverBorderWidth: 2,
            tension: 0.38,
            fill: true,
            yAxisID: "y1",
            order: 1,
          },
        ],
      },
      options: {
        animation: { duration: 600, easing: "easeOutQuart" },
        interaction: { mode: "index", intersect: false },
        onClick: (_event, elements) => {
          const bar = elements.find((element) => element.datasetIndex === 0);
          if (bar && currentMonthlyDays[bar.index]) {
            loadDayBreakdown(currentMonthlyDays[bar.index].stat_date);
          }
        },
        plugins: {
          legend: {
            position: "top",
            align: "center",
            labels: {
              boxWidth: 8,
              boxHeight: 8,
              usePointStyle: true,
              padding: 10,
              font: { size: 11, weight: "600" },
            },
          },
          tooltip: {
            backgroundColor: "rgba(15, 23, 42, 0.94)",
            titleColor: "#f8fafc",
            bodyColor: "#cbd5e1",
            borderColor: "rgba(255, 255, 255, 0.12)",
            borderWidth: 1,
            padding: 12,
            boxPadding: 6,
            usePointStyle: true,
            cornerRadius: 10,
            callbacks: {
              title: (items) => "Day " + items[0].label,
              label: (c) => `  ${c.dataset.label}: ${c.raw}h`,
            },
          },
        },
        scales: {
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 },
          },
          y: {
            position: "left",
            grid: { color: gridColor },
            border: { display: false },
            beginAtZero: true,
            title: {
              display: true,
              text: "hrs / day",
              font: { size: 11, weight: "600" },
            },
          },
          y1: {
            position: "right",
            grid: { display: false },
            border: { display: false },
            beginAtZero: true,
            title: {
              display: true,
              text: "cumulative hrs",
              font: { size: 11, weight: "600" },
            },
          },
        },
      },
    });
  }

  function renderQuarterly(qData) {
    if (!qData) return;
    currentQuarterlyData = qData;
    selectedQuarterYear = Number(qData.year) || selectedQuarterYear;

    const yrDisplay = document.getElementById("quarter-year-display");
    if (yrDisplay) yrDisplay.textContent = qData.year;

    updateStudyHint();

    // Render 4 Quarterly KPI Cards
    const cardsWrap = document.getElementById("quarterly-cards-container");
    if (cardsWrap && qData.quarters) {
      const annualMinutes = Math.max(1, Number(qData.annual_total_minutes) || 0);
      cardsWrap.innerHTML = qData.quarters
        .map((q) => {
          const qMinutes = Number(q.total_minutes) || 0;
          const pct = Math.min(100, Math.round((qMinutes / annualMinutes) * 100));
          const isCurr = q.is_current
            ? '<span class="qcard-current-badge">Current</span>'
            : "";
          const isSelected =
            selectedQuarterIndex === q.quarter ? "is-selected" : "";
          return `
            <div class="quarterly-card ${q.is_current ? "is-current" : ""} ${isSelected}" data-quarter="${q.quarter}" tabindex="0" role="button" aria-label="Quarter ${q.quarter}">
              <div class="qcard-top">
                <span class="qcard-q">${escapeHtml(q.label)}</span>
                <span class="qcard-months">${escapeHtml(q.months_label)}</span>
              </div>
              <div class="qcard-hours num">${q.total_hours}h</div>
              <div class="qcard-meta">
                <span>${q.completed_cycles} cycles</span>
                <span>·</span>
                <span>${q.active_days}d active</span>
                ${isCurr}
              </div>
              <div class="qcard-progress-bar">
                <div class="qcard-progress-fill" style="width: ${pct}%;"></div>
              </div>
            </div>
          `;
        })
        .join("");

      cardsWrap.querySelectorAll(".quarterly-card").forEach((card) => {
        card.addEventListener("click", () => {
          const qNum = Number(card.dataset.quarter);
          selectedQuarterIndex = selectedQuarterIndex === qNum ? null : qNum;
          renderQuarterly(currentQuarterlyData);
        });
        card.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            card.click();
          }
        });
      });
    }

    // Render / Update Chart.js Bar Chart
    const ctx = document.getElementById("chart-quarterly");
    if (!ctx || !qData.quarters) return;

    const labels = qData.quarters.map((q) => `${q.label} (${q.months_label})`);
    const studyHours = qData.quarters.map((q) => q.study_hours);
    const revisionHours = qData.quarters.map((q) => q.revision_hours);

    if (quarterlyChart) {
      quarterlyChart.data.labels = labels;
      quarterlyChart.data.datasets[0].data = studyHours;
      quarterlyChart.data.datasets[1].data = revisionHours;
      quarterlyChart.update();
    } else {
      const c2d = ctx.getContext("2d");
      const amberGrad = c2d.createLinearGradient(0, 0, 0, 180);
      amberGrad.addColorStop(0, "#fbbf24");
      amberGrad.addColorStop(1, "#d97706");

      const tealGrad = c2d.createLinearGradient(0, 0, 0, 180);
      tealGrad.addColorStop(0, "#34d399");
      tealGrad.addColorStop(1, "#059669");

      quarterlyChart = new Chart(ctx, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Study hours",
              data: studyHours,
              backgroundColor: amberGrad,
              hoverBackgroundColor: "#fde047",
              borderRadius: 6,
              maxBarThickness: 42,
              stack: "q_stack",
            },
            {
              label: "Revision hours",
              data: revisionHours,
              backgroundColor: tealGrad,
              hoverBackgroundColor: "#6ee7b7",
              borderRadius: 6,
              maxBarThickness: 42,
              stack: "q_stack",
            },
          ],
        },
        options: {
          animation: { duration: 600, easing: "easeOutQuart" },
          interaction: { mode: "index", intersect: false },
          onClick: (_event, elements) => {
            if (elements && elements.length) {
              const idx = elements[0].index;
              const qNum = idx + 1;
              selectedQuarterIndex =
                selectedQuarterIndex === qNum ? null : qNum;
              renderQuarterly(currentQuarterlyData);
            }
          },
          plugins: {
            legend: {
              position: "top",
              align: "center",
              labels: {
                boxWidth: 8,
                boxHeight: 8,
                usePointStyle: true,
                padding: 10,
                font: { size: 11, weight: "600" },
              },
            },
            tooltip: {
              backgroundColor: "rgba(15, 23, 42, 0.94)",
              titleColor: "#f8fafc",
              bodyColor: "#cbd5e1",
              borderColor: "rgba(255, 255, 255, 0.12)",
              borderWidth: 1,
              padding: 12,
              boxPadding: 6,
              usePointStyle: true,
              cornerRadius: 10,
              callbacks: {
                title: (items) => items[0].label,
                label: (c) => `  ${c.dataset.label}: ${c.raw}h`,
                afterBody: (items) => {
                  const qIdx = items[0].dataIndex;
                  const q = currentQuarterlyData?.quarters?.[qIdx];
                  if (!q) return [];
                  return [
                    `  Total quarter time: ${q.total_hours}h (${fmtMinutes(q.total_minutes)})`,
                    `  Completed cycles: ${q.completed_cycles}`,
                    `  Active study days: ${q.active_days} days`,
                  ];
                },
              },
            },
          },
          scales: {
            x: {
              stacked: true,
              grid: { display: false },
              border: { display: false },
              ticks: { font: { size: 11, weight: "600" } },
            },
            y: {
              stacked: true,
              grid: { color: gridColor },
              border: { display: false },
              beginAtZero: true,
              title: {
                display: true,
                text: "Hours invested",
                font: { size: 11, weight: "600" },
              },
            },
          },
        },
      });
    }

    // Render Summary Strip
    const summaryStrip = document.getElementById("quarterly-summary-strip");
    if (summaryStrip) {
      if (selectedQuarterIndex && qData.quarters[selectedQuarterIndex - 1]) {
        const sq = qData.quarters[selectedQuarterIndex - 1];
        summaryStrip.innerHTML = `
          <div class="q-stat-item">
            <span class="muted font-bold">Selected:</span>
            <span class="q-stat-badge"><strong>${sq.label} (${sq.months_label})</strong> &middot; ${sq.total_hours}h total</span>
          </div>
          <div class="q-stat-item">
            ${sq.months
              .map(
                (m) =>
                  `<span class="q-stat-badge">${m.name}: <strong class="num">${m.total_hours}h</strong></span>`,
              )
              .join("")}
          </div>
          <button type="button" class="btn btn-ghost btn-sm" id="btn-clear-quarter-filter" style="padding: 2px 8px; font-size: 11px;">Reset filter</button>
        `;
        document
          .getElementById("btn-clear-quarter-filter")
          ?.addEventListener("click", () => {
            selectedQuarterIndex = null;
            renderQuarterly(currentQuarterlyData);
          });
      } else {
        const bestQ = qData.quarters[qData.best_quarter - 1];
        summaryStrip.innerHTML = `
          <div class="q-stat-item">
            <span class="muted">Annual Total:</span>
            <strong class="num q-stat-val">${qData.annual_total_hours}h</strong>
            <span class="muted">(${fmtMinutes(qData.annual_total_minutes)})</span>
          </div>
          <div class="q-stat-item">
            <span class="muted">Cycles:</span>
            <strong class="num q-stat-val">${qData.annual_completed_cycles} completed</strong>
          </div>
          <div class="q-stat-item">
            <span class="muted">Active Days:</span>
            <strong class="num q-stat-val">${qData.annual_active_days} days</strong>
          </div>
          <div class="q-stat-badge">
            <span class="muted">Top Quarter:</span>
            <strong class="num text-amber">Q${qData.best_quarter}</strong>
            <span class="muted">(${bestQ ? bestQ.total_hours : 0}h)</span>
          </div>
        `;
      }
    }
  }

  function renderHeatmap(hData) {
    if (!hData || !hData.weeks) return;
    currentHeatmapData = hData;

    if (hData.summary) {
      const activeEl = document.getElementById("heatmap-active-days");
      const cyclesEl = document.getElementById("heatmap-cycles-count");
      const hoursEl = document.getElementById("heatmap-total-hours");
      if (activeEl) activeEl.textContent = hData.summary.total_active_days;
      if (cyclesEl) cyclesEl.textContent = hData.summary.total_completed_cycles;
      if (hoursEl) hoursEl.textContent = hData.summary.total_hours;
    }

    const container = document.getElementById("heatmap-matrix");
    if (!container) return;

    let tooltip = document.getElementById("heatmap-tooltip");
    if (!tooltip) {
      tooltip = document.createElement("div");
      tooltip.id = "heatmap-tooltip";
      tooltip.className = "heatmap-tooltip";
      document.body.appendChild(tooltip);
    }

    let weeksHtml = "";
    hData.weeks.forEach((week) => {
      week.forEach((day) => {
        const isFutureClass = day.is_future ? "is-future" : "";
        const isTodayClass = day.is_today ? "is-today" : "";
        const subjectsJson = JSON.stringify(day.subjects || []).replace(
          /"/g,
          "&quot;",
        );
        weeksHtml += `<div class="heatmap-cell level-${day.level} ${isFutureClass} ${isTodayClass}"
          data-date="${day.date}"
          data-level="${day.level}"
          data-day-name="${day.month_name} ${day.day_num}, ${day.year}"
          data-total-minutes="${day.total_minutes}"
          data-study-minutes="${day.study_minutes}"
          data-revision-minutes="${day.revision_minutes}"
          data-completed-cycles="${day.completed_cycles}"
          data-is-future="${day.is_future ? "1" : "0"}"
          data-subjects="${subjectsJson}"
          tabindex="${day.is_future ? "-1" : "0"}"
          role="gridcell"
          aria-label="${day.date}"></div>`;
      });
    });

    let monthsHtml = "";
    if (hData.month_labels) {
      hData.month_labels.forEach((ml) => {
        monthsHtml += `<span class="heatmap-month-label" style="grid-column: ${ml.col + 1};">${escapeHtml(ml.name)}</span>`;
      });
    }

    container.innerHTML = `
      <div class="heatmap-grid-layout">
        <div class="heatmap-day-labels" aria-hidden="true">
          <span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span>
        </div>
        <div class="heatmap-cols-container">
          <div class="heatmap-weeks-row">
            ${weeksHtml}
          </div>
          <div class="heatmap-months-row">
            ${monthsHtml}
          </div>
        </div>
      </div>
    `;

    container.querySelectorAll(".heatmap-cell").forEach((cell) => {
      cell.addEventListener("mouseenter", () => {
        const isFuture = cell.dataset.isFuture === "1";
        const dateStr = cell.dataset.date;
        const dayName = cell.dataset.dayName;
        const totalMin = Number(cell.dataset.totalMinutes) || 0;
        const studyMin = Number(cell.dataset.studyMinutes) || 0;
        const revMin = Number(cell.dataset.revisionMinutes) || 0;
        const cycles = Number(cell.dataset.completedCycles) || 0;
        let subjects = [];
        try {
          subjects = JSON.parse(cell.dataset.subjects || "[]");
        } catch (e) {}

        const parsedDate = new Date(dateStr + "T00:00:00");
        const weekday = parsedDate.toLocaleDateString(undefined, {
          weekday: "long",
        });

        let content = `<div class="ht-date">${weekday}, ${dayName}</div>`;
        if (isFuture) {
          content += `<div class="ht-sub">Upcoming date</div>`;
        } else if (totalMin === 0) {
          content += `<div class="ht-sub">No study activity recorded</div>`;
        } else {
          content += `<div class="ht-main">${cycles} cycle${cycles === 1 ? "" : "s"} completed &middot; ${fmtMinutes(totalMin)}</div>`;
          content += `<div class="ht-sub">${studyMin}m study + ${revMin}m revision</div>`;
          if (subjects.length > 0) {
            content += `<div class="ht-subjects">`;
            subjects.forEach((s) => {
              content += `<div class="ht-subject-item">
                <span class="ht-dot" style="background:${s.color_hex};"></span>
                <span>${escapeHtml(s.name)}: <strong>${fmtMinutes(s.minutes)}</strong></span>
              </div>`;
            });
            content += `</div>`;
          }
        }

        tooltip.innerHTML = content;
        const rect = cell.getBoundingClientRect();
        tooltip.style.left = `${Math.round(rect.left + rect.width / 2)}px`;
        tooltip.style.top = `${Math.round(rect.top - 8)}px`;
        tooltip.classList.add("is-visible");
      });

      cell.addEventListener("mouseleave", () => {
        tooltip.classList.remove("is-visible");
      });

      cell.addEventListener("click", () => {
        if (cell.dataset.isFuture === "1") return;
        tooltip.classList.remove("is-visible");
        loadDayBreakdown(cell.dataset.date);
        const breakdownEl = document.getElementById("day-breakdown");
        if (breakdownEl) {
          breakdownEl.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
      });

      cell.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          cell.click();
        }
      });
    });
  }

  function renderSubjectProgress(rows) {
    const wrap = document.getElementById("subject-progress-rows");
    if (!wrap) return;
    if (!rows || !rows.length) {
      wrap.innerHTML =
        '<div class="empty" style="padding: 32px 12px;"><p>Complete a cycle and this fills in on its own.</p></div>';
      return;
    }
    const max = Math.max(...rows.map((r) => Number(r.total_minutes)));
    wrap.innerHTML = rows
      .map((d) => {
        const pct =
          max > 0 ? Math.round((Number(d.total_minutes) / max) * 100) : 0;
        return `
        <div class="progress-row subject-progress-row" data-subject-id="${d.id}" tabindex="0" role="button" aria-expanded="false">
          <div class="progress-row-top">
            <span class="dot" style="background:${d.color_hex};"></span>
            <span class="progress-row-name">${d.name}</span>
            <span class="progress-row-value num" data-minutes="${Number(d.total_minutes) || 0}">${fmtMinutes(d.total_minutes)}</span>
            <button type="button" class="subject-delete" data-subject-id="${d.id}" title="Delete subject" aria-label="Delete this subject">Delete</button>
          </div>
          <div class="bar-track">
            <div class="bar-fill" style="width:${pct}%; background:${d.color_hex};"></div>
          </div>
          <div class="material-progress-list" data-materials-for="${d.id}" hidden></div>
        </div>`;
      })
      .join("");
  }

  function escapeHtml(value) {
    return String(value).replace(
      /[&<>'"]/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[char],
    );
  }

  async function toggleMaterials(row) {
    const subjectId = row.dataset.subjectId;
    const list = row.querySelector(`[data-materials-for="${subjectId}"]`);
    if (!list) return;
    const opening = list.hidden;
    list.hidden = !opening;
    list.classList.toggle("is-open", opening);
    row.setAttribute("aria-expanded", String(opening));
    if (!opening || list.dataset.loaded === "1") return;

    list.innerHTML =
      '<div class="material-progress-loading">Loading materials...</div>';
    try {
      const [materials, unassigned] = await Promise.all([
        api(`api/materials.php?subject_id=${encodeURIComponent(subjectId)}`),
        api(
          `api/materials.php?subject_id=${encodeURIComponent(subjectId)}&unassigned=1`,
        ),
      ]);
      list.dataset.loaded = "1";
      if (!materials.length && !unassigned.total_minutes) {
        list.innerHTML =
          '<div class="material-progress-empty">No materials logged for this subject.</div>';
        return;
      }
      const subjectMinutes = Math.max(
        1,
        Number(row.querySelector(".progress-row-value")?.dataset.minutes || 0),
      );
      let html = materials
        .map((material) => {
          const minutes = Number(material.total_minutes) || 0;
          const percent = Math.min(
            100,
            Math.round((minutes / subjectMinutes) * 100),
          );
          return `<div class="material-progress-row" data-material-id="${material.id}">
          <div class="progress-row-top">
            <span class="material-progress-name">${escapeHtml(material.title)}</span>
            <span class="progress-row-value num">${fmtMinutes(minutes)}</span>
          </div>
          <div class="bar-track"><div class="bar-fill material-bar" style="width:${percent}%;"></div></div>
          <div class="material-progress-meta">${material.total_cycles} cycles · ${escapeHtml(material.status.replace("_", " "))}
            <button type="button" class="material-delete" data-material-id="${material.id}">Delete</button>
          </div>
        </div>`;
        })
        .join("");

      if (unassigned.total_minutes > 0) {
        const percent = Math.min(
          100,
          Math.round((unassigned.total_minutes / subjectMinutes) * 100),
        );
        html += `<div class="material-progress-row" data-unassigned-for="${subjectId}">
          <div class="progress-row-top">
            <span class="material-progress-name material-progress-name-muted">No material (general sessions)</span>
            <span class="progress-row-value num">${fmtMinutes(unassigned.total_minutes)}</span>
          </div>
          <div class="bar-track"><div class="bar-fill material-bar" style="width:${percent}%;"></div></div>
          <div class="material-progress-meta">${unassigned.total_cycles} cycles logged with no material picked
            <button type="button" class="material-delete unassigned-delete" data-subject-id="${subjectId}">Delete</button>
          </div>
        </div>`;
      }

      list.innerHTML = html;
      list.querySelectorAll(".material-delete[data-material-id]").forEach((button) => {
        button.addEventListener("click", async (event) => {
          event.stopPropagation();
          if (
            !confirm("Delete this material and all cycles logged against it?")
          )
            return;
          await api("api/materials.php", {
            method: "POST",
            body: { action: "delete", id: button.dataset.materialId },
          });
          window.location.reload();
        });
      });
      list.querySelectorAll(".unassigned-delete").forEach((button) => {
        button.addEventListener("click", async (event) => {
          event.stopPropagation();
          if (
            !confirm(
              "Delete all general sessions logged for this subject with no material picked?",
            )
          )
            return;
          await api("api/cycle.php", {
            method: "POST",
            body: {
              action: "delete_unassigned",
              subject_id: button.dataset.subjectId,
            },
          });
          window.location.reload();
        });
      });
    } catch (error) {
      list.innerHTML =
        '<div class="material-progress-empty">Could not load materials.</div>';
    }
  }

  document
    .getElementById("subject-progress-rows")
    ?.addEventListener("click", async (event) => {
      const deleteBtn = event.target.closest(".subject-delete");
      if (deleteBtn) {
        event.stopPropagation();
        if (
          !confirm(
            "Delete this subject? Its materials and cycles will be removed too.",
          )
        )
          return;
        await api("api/subjects.php", {
          method: "POST",
          body: { action: "delete", id: deleteBtn.dataset.subjectId },
        });
        showToast("Subject deleted.");
        window.location.reload();
        return;
      }
      const row = event.target.closest(".subject-progress-row");
      if (row && !event.target.closest("button")) toggleMaterials(row);
    });
  document
    .getElementById("subject-progress-rows")
    ?.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      const row = event.target.closest(".subject-progress-row");
      if (!row) return;
      event.preventDefault();
      toggleMaterials(row);
    });

  function renderNeglected(rows) {
    const wrap = document.getElementById("neglected-rows");
    if (!wrap) return;
    if (!rows.length) {
      wrap.innerHTML =
        '<div class="empty"><p>Add a subject to start tracking neglect.</p></div>';
      return;
    }
    wrap.innerHTML = rows
      .map(
        (n, i) => `
      <div class="row">
        <span class="row-rank num">${i + 1}</span>
        <span class="row-title">${n.name}</span>
        <span class="row-value">${n.last_studied_at ? Math.round(n.days_since_studied) + " days" : "never studied"}</span>
      </div>`,
      )
      .join("");
  }

  function renderRecent(rows) {
    const table = document.getElementById("recent-table");
    if (!table || !rows.length) return;
    const tbody = table.querySelector("tbody");
    const statusBadge = (s) =>
      s === "completed"
        ? '<span class="badge badge-amber">completed</span>'
        : s === "in_progress"
          ? '<span class="badge badge-teal">in progress</span>'
          : '<span class="badge badge-muted">abandoned</span>';
    tbody.innerHTML = rows
      .map(
        (c) => `
      <tr>
        <td><span class="dot" style="background:${c.color_hex}; display:inline-block; margin-right:8px;"></span>${c.subject_name}</td>
        <td class="muted">${c.material_title ?? "—"}</td>
        <td class="muted">${c.cycle_date}</td>
        <td class="num">${c.study_minutes}m</td>
        <td class="num">${c.revision_minutes}m</td>
        <td>${statusBadge(c.status)}</td>
      </tr>`,
      )
      .join("");
  }

  function setBar(id, pct) {
    const el = document.getElementById(id);
    if (el) el.style.width = Math.max(0, Math.min(100, pct)) + "%";
  }
  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function renderGoals(s) {
    if (s.goal) {
      const pct =
        s.goal.target_cycles > 0
          ? Math.min(
              100,
              Math.round(
                (s.today.completed_cycles / s.goal.target_cycles) * 100,
              ),
            )
          : 0;
      setText(
        "daily-goal-cycles-label",
        `${s.today.completed_cycles} of ${s.goal.target_cycles} cycles completed today`,
      );
      setBar("daily-goal-cycles-bar", pct);
      if (s.goal.target_minutes) {
        setText(
          "daily-goal-minutes-label",
          `${fmtMinutes(s.today.total_minutes)} of ${fmtMinutes(s.goal.target_minutes)} minutes`,
        );
        setBar(
          "daily-goal-minutes-bar",
          Math.round((s.today.total_minutes / s.goal.target_minutes) * 100),
        );
      }
    }
    if (s.weekly_goal) {
      const pct =
        s.weekly_goal.target_cycles > 0
          ? Math.min(
              100,
              Math.round(
                (s.week.completed_cycles / s.weekly_goal.target_cycles) * 100,
              ),
            )
          : 0;
      setText(
        "weekly-goal-cycles-label",
        `${s.week.completed_cycles} of ${s.weekly_goal.target_cycles} cycles completed this week`,
      );
      setBar("weekly-goal-cycles-bar", pct);
      if (s.weekly_goal.target_minutes) {
        setText(
          "weekly-goal-minutes-label",
          `${fmtMinutes(s.week.total_minutes)} of ${fmtMinutes(s.weekly_goal.target_minutes)} minutes`,
        );
        setBar(
          "weekly-goal-minutes-bar",
          Math.round(
            (s.week.total_minutes / s.weekly_goal.target_minutes) * 100,
          ),
        );
      }
    }
  }

  renderMonthly(data.monthly.days);
  if (data.quarterly) renderQuarterly(data.quarterly);
  if (data.heatmap) renderHeatmap(data.heatmap);
  renderSubjectProgress(data.distribution);

  /* ---------- tab switching (Monthly / Quarterly) ---------- */

  const tabMonthly = document.getElementById("tab-monthly");
  const tabQuarterly = document.getElementById("tab-quarterly");
  const viewMonthlyWrap = document.getElementById("view-monthly-wrap");
  const viewQuarterlyWrap = document.getElementById("view-quarterly-wrap");
  const monthlyControls = document.getElementById("monthly-controls");
  const quarterlyControls = document.getElementById("quarterly-controls");

  function switchStudyTab(tab) {
    activeStudyTab = tab;
    if (tab === "monthly") {
      tabMonthly?.classList.add("active");
      tabMonthly?.setAttribute("aria-selected", "true");
      tabQuarterly?.classList.remove("active");
      tabQuarterly?.setAttribute("aria-selected", "false");

      if (viewMonthlyWrap) viewMonthlyWrap.style.display = "block";
      if (viewQuarterlyWrap) viewQuarterlyWrap.style.display = "none";
      if (monthlyControls) monthlyControls.style.display = "flex";
      if (quarterlyControls) quarterlyControls.style.display = "none";

      updateStudyHint();
      if (monthlyChart) monthlyChart.resize();
    } else {
      tabQuarterly?.classList.add("active");
      tabQuarterly?.setAttribute("aria-selected", "true");
      tabMonthly?.classList.remove("active");
      tabMonthly?.setAttribute("aria-selected", "false");

      if (viewMonthlyWrap) viewMonthlyWrap.style.display = "none";
      if (viewQuarterlyWrap) viewQuarterlyWrap.style.display = "block";
      if (monthlyControls) monthlyControls.style.display = "none";
      if (quarterlyControls) quarterlyControls.style.display = "inline-flex";

      updateStudyHint();
      if (currentQuarterlyData) {
        renderQuarterly(currentQuarterlyData);
      }
      if (quarterlyChart) quarterlyChart.resize();
    }
  }

  tabMonthly?.addEventListener("click", () => switchStudyTab("monthly"));
  tabQuarterly?.addEventListener("click", () => switchStudyTab("quarterly"));

  /* ---------- month navigation ---------- */

  async function loadMonth(month) {
    selectedMonth = month;
    updateStudyHint();
    try {
      const s = await api("api/stats.php?month=" + encodeURIComponent(month));
      renderMonthly(s.monthly.days);
    } catch (e) {
      /* keep current chart on failure */
    }
  }

  function shiftMonth(delta) {
    const [y, m] = selectedMonth.split("-").map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const next =
      d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0");
    const cap = new Date();
    const capStr =
      cap.getFullYear() + "-" + String(cap.getMonth() + 1).padStart(2, "0");
    if (next > capStr) return;
    const picker = document.getElementById("month-picker");
    if (picker) picker.value = next;
    loadMonth(next);
  }

  document.getElementById("month-picker")?.addEventListener("change", (e) => {
    if (e.target.value) loadMonth(e.target.value);
  });
  document
    .getElementById("month-prev")
    ?.addEventListener("click", () => shiftMonth(-1));
  document
    .getElementById("month-next")
    ?.addEventListener("click", () => shiftMonth(1));

  /* ---------- quarter navigation ---------- */

  async function loadQuarterYear(yr) {
    selectedQuarterYear = yr;
    try {
      const s = await api(
        "api/stats.php?quarter_year=" + encodeURIComponent(yr),
      );
      if (s.quarterly) renderQuarterly(s.quarterly);
    } catch (e) {}
  }

  document.getElementById("quarter-prev")?.addEventListener("click", () => {
    loadQuarterYear(selectedQuarterYear - 1);
  });
  document.getElementById("quarter-next")?.addEventListener("click", () => {
    const curYear = new Date().getFullYear();
    if (selectedQuarterYear < curYear) {
      loadQuarterYear(selectedQuarterYear + 1);
    }
  });

  /* ---------- heatmap controls ---------- */

  document
    .getElementById("heatmap-year-select")
    ?.addEventListener("change", async (e) => {
      selectedHeatmapYear = e.target.value;
      try {
        const s = await api(
          "api/stats.php?heatmap_year=" +
            encodeURIComponent(selectedHeatmapYear),
        );
        if (s.heatmap) renderHeatmap(s.heatmap);
      } catch (err) {}
    });

  /* ---------- live refresh (polls the DB directly, so it's always current) ---------- */

  async function refresh() {
    try {
      const queryParams = new URLSearchParams({
        month: selectedMonth,
      });
      if (selectedQuarterYear) {
        queryParams.set("quarter_year", String(selectedQuarterYear));
      }
      if (selectedHeatmapYear) {
        queryParams.set("heatmap_year", String(selectedHeatmapYear));
      }

      const s = await api("api/stats.php?" + queryParams.toString());
      document.getElementById("hero-completed").textContent =
        s.today.completed_cycles;
      document.getElementById("hero-study").textContent = fmtMinutes(
        s.today.total_study_minutes,
      );
      document.getElementById("hero-revision").textContent = fmtMinutes(
        s.today.total_revision_minutes,
      );
      document.getElementById("hero-streak").textContent =
        s.streak.current_streak;
      document.getElementById("hero-subjects").textContent =
        s.today.subjects_touched;

      const target = s.goal ? s.goal.target_cycles : data.goalTarget;
      const pct =
        target > 0
          ? Math.min(100, Math.round((s.today.completed_cycles / target) * 100))
          : 0;
      document.getElementById("hero-goal-pct").textContent = pct;
      document.getElementById("hero-bar").style.width = pct + "%";

      renderMonthly(s.monthly.days);
      if (s.quarterly) renderQuarterly(s.quarterly);
      if (s.heatmap) renderHeatmap(s.heatmap);
      renderSubjectProgress(s.distribution);
      renderNeglected(s.neglected);
      renderRecent(s.recent);
      renderGoals(s);
    } catch (e) {
      /* silent — keep last known state on transient errors */
    }
  }

  setInterval(refresh, 15000);
})();
