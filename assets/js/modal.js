(() => {
  document.querySelectorAll("[data-modal-open]").forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const modal = document.getElementById(trigger.getAttribute("data-modal-open"));
      if (modal instanceof HTMLDialogElement) modal.showModal();
    });
  });
  document.querySelectorAll("[data-modal-close]").forEach((trigger) => {
    trigger.addEventListener("click", () => {
      const modal = trigger.closest("dialog");
      if (modal instanceof HTMLDialogElement) modal.close();
    });
  });
})();
