(function () {
  const form = document.getElementById("subject-form");
  if (!form) return;

  const idField = document.getElementById("subject_id");
  const nameField = document.getElementById("subject_name");
  const colorField = document.getElementById("subject_color");
  const colorRow = document.getElementById("color-row");
  const title = document.getElementById("subject-modal-title");

  function setColor(c) {
    colorField.value = c;
    [...colorRow.children].forEach((dot) => {
      dot.style.outline =
        dot.dataset.color === c ? "2px solid #edeae2" : "none";
      dot.style.outlineOffset = "2px";
    });
  }
  colorRow.addEventListener("click", (e) => {
    if (e.target.dataset.color) setColor(e.target.dataset.color);
  });
  setColor(colorField.value);

  document.querySelectorAll(".btn-edit").forEach((btn) => {
    btn.addEventListener("click", () => {
      idField.value = btn.dataset.id;
      nameField.value = btn.dataset.name;
      setColor(btn.dataset.color);
      title.textContent = "Edit subject";
      openModal("subject-modal");
    });
  });

  document.getElementById("btn-new-subject")?.addEventListener("click", () => {
    // "New subject" top button resets the form
    idField.value = "";
    nameField.value = "";
    setColor("#E8A33D");
    title.textContent = "New subject";
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const isEdit = !!idField.value;
    await api("api/subjects.php", {
      method: "POST",
      body: {
        action: isEdit ? "update" : "create",
        id: idField.value || undefined,
        name: nameField.value.trim(),
        color_hex: colorField.value,
      },
    });
    showToast(isEdit ? "Subject updated." : "Subject added.");
    window.location.reload();
  });

  document.querySelectorAll(".btn-archive").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const archived = btn.dataset.archived === "1" ? 0 : 1;
      await api("api/subjects.php", {
        method: "POST",
        body: { action: "archive", id: btn.dataset.id, archived },
      });
      window.location.reload();
    });
  });

  document.querySelectorAll(".btn-delete").forEach((btn) => {
    btn.addEventListener("click", async () => {
      if (
        !confirm(
          "Delete this subject? Its materials and cycles will be removed too.",
        )
      )
        return;
      await api("api/subjects.php", {
        method: "POST",
        body: { action: "delete", id: btn.dataset.id },
      });
      showToast("Subject deleted.");
      window.location.reload();
    });
  });
})();
