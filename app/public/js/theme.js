(function () {
  const root = document.documentElement;
  const KEY = "tw-theme";
  function apply(t) {
    root.setAttribute("data-bs-theme", t);
    localStorage.setItem(KEY, t);
    const ic = document.querySelector("#auth-theme-ico");
    if (ic) ic.className = "bi " + (t === "dark" ? "bi-moon-stars" : "bi-sun");
  }
  apply(localStorage.getItem(KEY) || "dark");

  document.addEventListener("DOMContentLoaded", () => {
    apply(localStorage.getItem(KEY) || "dark");

    const tbtn = document.getElementById("auth-theme-btn");
    if (tbtn) tbtn.addEventListener("click", () =>
      apply(root.getAttribute("data-bs-theme") === "dark" ? "light" : "dark"));
  });
})();
