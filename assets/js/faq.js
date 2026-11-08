(() => {
  document.querySelectorAll(".faq-list").forEach((list) => {
    list.addEventListener("toggle", (event) => {
      const opened = event.target;
      if (!(opened instanceof HTMLDetailsElement) || !opened.open) return;
      list.querySelectorAll("details").forEach((item) => {
        if (item !== opened) item.open = false;
      });
    }, true);
  });
})();
