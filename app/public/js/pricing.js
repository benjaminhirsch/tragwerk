(function () {
  document.addEventListener("DOMContentLoaded", () => {
    const sw = document.getElementById("bill-switch");
    const lblM = document.getElementById("lbl-monthly");
    const lblY = document.getElementById("lbl-yearly");
    if (!sw || !lblM || !lblY) return;

    function setCycle(yearly) {
      document.querySelectorAll("[data-monthly]").forEach((el) => {
        el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly;
      });
      lblM.style.color = yearly ? "var(--text-muted)" : "var(--text)";
      lblY.style.color = yearly ? "var(--text)" : "var(--text-muted)";
    }

    // initial state = monthly
    setCycle(false);
    sw.addEventListener("change", () => setCycle(sw.checked));
  });
})();
