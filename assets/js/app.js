(() => {
  const config = window.PDFFORGE || {};
  document.documentElement.dataset.appReady = "true";
  window.PDFForge = {
    baseUrl: String(config.baseUrl || ""),
    csrfToken: String(config.csrfToken || ""),
  };
})();
