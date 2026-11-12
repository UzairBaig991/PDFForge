(() => {
  const config = window.PDFFORGE || {};
  document.documentElement.dataset.appReady = "true";
  window.PDFForge = {
    baseUrl: String(config.baseUrl || ""),
    csrfToken: String(config.csrfToken || ""),
  };
})();

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

(() => {
  const header = document.querySelector(".site-header");
  const menuButton = document.querySelector(".menu-toggle");
  const menu = document.querySelector(".nav-menu");
  const updateScrollState = () => header?.classList.toggle("is-scrolled", window.scrollY > 4);
  updateScrollState();
  window.addEventListener("scroll", updateScrollState, { passive: true });

  if (!menuButton || !menu) return;
  menuButton.addEventListener("click", () => {
    const expanded = menuButton.getAttribute("aria-expanded") === "true";
    menuButton.setAttribute("aria-expanded", String(!expanded));
    menuButton.setAttribute("aria-label", expanded ? "Open menu" : "Close menu");
    menu.classList.toggle("is-open", !expanded);
  });
  menu.addEventListener("click", (event) => {
    if (event.target instanceof HTMLAnchorElement) {
      menu.classList.remove("is-open");
      menuButton.setAttribute("aria-expanded", "false");
      menuButton.setAttribute("aria-label", "Open menu");
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && menu.classList.contains("is-open")) {
      menu.classList.remove("is-open");
      menuButton.setAttribute("aria-expanded", "false");
      menuButton.focus();
    }
  });
})();

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

(() => {
  const maxBytes = Number(window.PDFFORGE?.maxUploadSize || 50 * 1024 * 1024);
  const csrf = String(window.PDFForge?.csrfToken || "");
  const baseUrl = String(window.PDFForge?.baseUrl || "");

  document.querySelectorAll("[data-upload-form]").forEach((form) => {
    const picker = form.querySelector("[data-file-input]");
    const homeToolTabs = [...document.querySelectorAll("[data-home-tool]")];
    const autoUpload = homeToolTabs.length > 0;
    const convertButton = autoUpload ? null : Object.assign(document.createElement("button"), {
      type: "button",
      className: "button button-small convert-button",
      textContent: "Upload & convert",
      hidden: true,
    });
    const homeToolDescription = document.querySelector("[data-home-tool-description]");
    const homeNoteTitle = document.querySelector("[data-home-note-title]");
    const homeNoteDescription = document.querySelector("[data-home-note-description]");
    const isImageUpload = form.dataset.imageUpload === "true";
    const multipleAllowed = picker.multiple;
    const zone = form.querySelector("[data-drop-zone]");
    const selectButton = form.querySelector("[data-select-file]");
    const fileRow = form.querySelector("[data-upload-file]");
    const fileName = form.querySelector("[data-file-name]");
    const statusBox = form.querySelector("[data-upload-status]");
    const statusText = form.querySelector("[data-status-text]");
    const progress = form.querySelector("[data-progress]");
    const resultBox = form.querySelector("[data-result]");
    const cancelButton = form.querySelector("[data-cancel]");
    const removeButton = form.querySelector("[data-remove-file]");
    const retryButton = form.querySelector("[data-retry]");
    const uploadTitle = form.querySelector("[data-upload-title]");
    let selectedFiles = [];
    let jobId = "";
    let request = null;
    let processing = false;

    homeToolTabs.forEach((tab) => tab.addEventListener("click", () => {
      if (jobId || selectedFiles.length || request || processing) return;
      const tool = tab.dataset.homeTool;
      const isWord = tool === "pdf-to-word";
      form.dataset.tool = tool;
      form.querySelector('input[name="tool"]').value = tool;
      homeToolTabs.forEach((option) => {
        const active = option === tab;
        option.classList.toggle("is-active", active);
        option.setAttribute("aria-selected", String(active));
      });
      document.querySelector("#upload-title").textContent = isWord ? "PDF to Word" : "PDF to JPG";
      homeToolDescription.textContent = isWord
        ? "Turn selectable text into an editable Word document"
        : "Turn every page into a clear image";
      homeNoteTitle.textContent = isWord ? "One file at a time" : "One page at a time";
      homeNoteDescription.textContent = isWord ? "editable Word document" : "or a ZIP of every page";
      form.querySelector("[data-select-file]").textContent = "Select PDF file";
      form.querySelector(".upload-limit").textContent = isWord
        ? "PDF · one file at a time · up to " + Math.round(maxBytes / 1024 / 1024) + " MB"
        : "PDF · combined size up to " + (maxBytes / 1024 / 1024).toFixed(1) + " MB";
    }));

    const setStatus = (message, isError = false) => {
      statusBox.hidden = false;
      statusText.textContent = message;
      statusText.classList.toggle("danger-text", isError);
    };

    const clearResults = () => {
      resultBox.replaceChildren();
      resultBox.hidden = true;
    };

    const reset = () => {
      if (request) request.abort();
      request = null;
      selectedFiles = [];
      jobId = "";
      processing = false;
      picker.value = "";
      fileRow.hidden = true;
      statusBox.hidden = true;
      progress.hidden = false;
      progress.value = 0;
      retryButton.hidden = true;
      cancelButton.hidden = true;
      if (convertButton) convertButton.hidden = true;
      clearResults();
      uploadTitle.textContent = isImageUpload ? "Drag & drop your images here" : "Drag & drop your PDF here";
    };

    const showError = (message, canRetry = false) => {
      setStatus(message, true);
      progress.hidden = true;
      cancelButton.hidden = true;
      retryButton.hidden = !canRetry;
    };

    const chooseFiles = async (files) => {
      const chosenFiles = [...(files || [])];
      reset();
      if (!chosenFiles.length) return;
      if ((!multipleAllowed && chosenFiles.length !== 1) || chosenFiles.length > 20) {
        showError(multipleAllowed ? "Choose no more than 20 files." : "Choose one file.", false);
        return;
      }
      if (chosenFiles.reduce((sum, file) => sum + file.size, 0) > maxBytes) {
        showError("The selected files exceed the " + Math.round(maxBytes / 1024 / 1024) + " MB combined upload limit.", false);
        return;
      }
      if (form.dataset.tool === "merge-pdf" && chosenFiles.length < 2) {
        showError("Choose at least two PDF files to merge.", false);
        return;
      }
      try {
        for (const file of chosenFiles) {
          if (file.size < 8) throw new Error("A selected file is too small to be valid.");
          const bytes = new Uint8Array(await file.slice(0, 8).arrayBuffer());
          const isPdf = file.name.toLowerCase().endsWith(".pdf")
            && new TextDecoder().decode(bytes.slice(0, 5)) === "%PDF-";
          const isJpeg = /\.(?:jpe?g)$/i.test(file.name)
            && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff;
          const isPng = /\.png$/i.test(file.name)
            && bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47;
          if (isImageUpload ? !(isJpeg || isPng) : !isPdf) {
            throw new Error(isImageUpload ? "Choose valid JPG or PNG image files." : "Choose valid PDF files.");
          }
        }
      } catch (error) {
        showError(error.message || "This browser could not validate the selected files.", false);
        return;
      }
      selectedFiles = chosenFiles;
      fileName.textContent = chosenFiles.map((file) => file.name).join(", ");
      fileRow.hidden = false;
      uploadTitle.textContent = "Ready to process";
      clearResults();
      retryButton.hidden = true;
      if (autoUpload) {
        startUpload();
      } else {
        setStatus("File selected. Choose Upload & convert to continue.", false);
        progress.hidden = true;
        convertButton.hidden = false;
      }
    };

    const renderResult = (payload, completedJobId) => {
      statusText.classList.remove("danger-text");
      const statusMessage = "Processing complete. Your result is ready to download.";
      setStatus(statusMessage);
      progress.hidden = true;
      retryButton.hidden = true;
      clearResults();
      const download = document.createElement("a");
      download.className = "button button-small";
      download.href = baseUrl + "/api/download.php?id=" + encodeURIComponent(completedJobId);
      download.textContent = "Download " + (payload.result_name || "file");
      resultBox.append(download);
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "button button-small button-outline";
      remove.textContent = "Delete files";
      remove.addEventListener("click", async () => {
        remove.disabled = true;
        try {
          const body = new URLSearchParams({ id: completedJobId });
          const response = await fetch(baseUrl + "/api/delete.php", {
            method: "POST",
            headers: { "X-CSRF-Token": csrf, "Content-Type": "application/x-www-form-urlencoded" },
            body,
          });
          const data = await response.json();
          if (!response.ok || !data.success) throw new Error(data.message || "Files could not be deleted.");
          reset();
          window.dispatchEvent(new CustomEvent("pdfforge:notice", { detail: { message: "Temporary files deleted." } }));
        } catch (error) {
          remove.disabled = false;
          showError(error.message || "Files could not be deleted.", false);
        }
      });
      resultBox.append(remove);
      resultBox.hidden = false;
    };

    const processJob = async () => {
      if (!jobId || processing) return;
      const currentJobId = jobId;
      processing = true;
      setStatus("Processing your files. This may take a moment…");
      progress.hidden = true;
      retryButton.hidden = true;
      try {
        const body = new URLSearchParams({ id: currentJobId });
        new FormData(form).forEach((value, key) => {
          if (typeof value === "string" && !["csrf_token", "tool"].includes(key)) body.set(key, value);
        });
        const response = await fetch(baseUrl + "/api/process.php", {
          method: "POST",
          headers: { "X-CSRF-Token": csrf, "Content-Type": "application/x-www-form-urlencoded" },
          body,
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || "PDF conversion failed.");
        if (jobId === currentJobId) renderResult(payload, currentJobId);
      } catch (error) {
        if (jobId === currentJobId) {
          if (error.message === "The requested job was not found or has expired.") {
            jobId = "";
            showError("This upload expired. Try again to upload the selected file.", true);
          } else {
            showError(error.message || "PDF conversion failed.", true);
          }
        }
      } finally {
        if (jobId === currentJobId) processing = false;
      }
    };

    const startUpload = () => {
      if (!selectedFiles.length) return;
      if (jobId) {
        processJob();
        return;
      }
      const xhr = new XMLHttpRequest();
      request = xhr;
      setStatus("Uploading…");
      progress.hidden = false;
      progress.value = 0;
      cancelButton.hidden = false;
      retryButton.hidden = true;
      xhr.open("POST", form.action);
      xhr.setRequestHeader("X-CSRF-Token", csrf);
      xhr.upload.addEventListener("progress", (event) => {
        if (event.lengthComputable) progress.value = Math.round((event.loaded / event.total) * 100);
      });
      xhr.addEventListener("load", () => {
        if (request !== xhr) return;
        request = null;
        cancelButton.hidden = true;
        let payload;
        try {
          payload = JSON.parse(xhr.responseText);
        } catch {
          showError("The server returned an invalid response.", true);
          return;
        }
        if (xhr.status < 200 || xhr.status >= 300 || !payload.success) {
          showError(payload.message || "The upload failed.", true);
          return;
        }
        jobId = payload.job_id;
        processJob();
      });
      xhr.addEventListener("error", () => {
        if (request !== xhr) return;
        request = null;
        showError("The upload failed because the server could not be reached.", true);
      }, { once: true });
      xhr.addEventListener("abort", () => {
        if (request !== xhr) return;
        request = null;
        cancelButton.hidden = true;
        showError("Upload canceled. Your selected PDF is ready to try again.", true);
      }, { once: true });
      const data = new FormData(form);
      data.delete("files[]");
      selectedFiles.forEach((file) => data.append("files[]", file));
      xhr.send(data);
    };

    selectButton.addEventListener("click", (event) => {
      event.stopPropagation();
      picker.click();
    });
    zone.addEventListener("click", (event) => {
      if (!(event.target instanceof HTMLButtonElement)) picker.click();
    });
    zone.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        picker.click();
      }
    });
    picker.addEventListener("change", () => chooseFiles(picker.files));
    ["dragenter", "dragover"].forEach((eventName) => zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.add("is-dragging");
    }));
    ["dragleave", "drop"].forEach((eventName) => zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.remove("is-dragging");
    }));
    zone.addEventListener("drop", (event) => chooseFiles(event.dataTransfer?.files));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      startUpload();
    });
    zone.addEventListener("dblclick", startUpload);
    if (convertButton) {
      selectButton.insertAdjacentElement("afterend", convertButton);
      convertButton.addEventListener("click", startUpload);
    }
    removeButton.addEventListener("click", reset);
    cancelButton.addEventListener("click", () => request?.abort());
    retryButton.addEventListener("click", () => {
      if (jobId) processJob();
      else startUpload();
    });
  });
})();

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
