(() => {
  const search = document.querySelector("[data-tool-search]");
  if (!search) return;
  const cards = [...document.querySelectorAll("[data-tool-card]")];
  const empty = document.querySelector("[data-search-empty]");
  search.addEventListener("input", () => {
    const query = search.value.trim().toLowerCase();
    const terms = query.split(/\s+/).filter(Boolean);
    let visible = 0;
    cards.forEach((card) => {
      const content = String(card.dataset.search || "").toLowerCase();
      const matches = terms.every((term) => {
        const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        return new RegExp("(?:^|[^a-z0-9])" + escaped + "(?=$|[^a-z0-9])").test(content);
      });
      card.hidden = !matches;
      if (matches) visible++;
    });
    document.querySelectorAll(".directory-section").forEach((section) => {
      section.hidden = !section.querySelector("[data-tool-card]:not([hidden])");
    });
    if (empty) empty.hidden = visible !== 0;
  });
})();
