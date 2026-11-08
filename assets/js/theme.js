(() => {
  const root = document.documentElement;
  const storageKey = "pdfforge-theme";
  let savedTheme = "light";
  try {
    savedTheme = localStorage.getItem(storageKey) || "light";
  } catch {
    savedTheme = "light";
  }
  root.dataset.theme = savedTheme === "dark" ? "dark" : "light";

  document.querySelectorAll(".theme-toggle").forEach((button) => {
    button.setAttribute("aria-pressed", String(root.dataset.theme === "dark"));
    button.addEventListener("click", () => {
      root.dataset.theme = root.dataset.theme === "dark" ? "light" : "dark";
      button.setAttribute("aria-pressed", String(root.dataset.theme === "dark"));
      try {
        localStorage.setItem(storageKey, root.dataset.theme);
      } catch {
        window.dispatchEvent(new CustomEvent("pdfforge:notice", {
          detail: { message: "Theme changed for this visit; browser storage is unavailable." },
        }));
      }
    });
  });
})();
