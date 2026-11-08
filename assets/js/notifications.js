(() => {
  let region;
  window.addEventListener("pdfforge:notice", (event) => {
    if (!region) {
      region = document.createElement("div");
      region.className = "toast-region";
      region.setAttribute("aria-live", "polite");
      region.setAttribute("aria-atomic", "true");
      document.body.append(region);
    }
    const toast = document.createElement("div");
    toast.className = "toast";
    toast.textContent = String(event.detail?.message || "");
    region.append(toast);
    window.setTimeout(() => toast.remove(), 4200);
  });
})();
