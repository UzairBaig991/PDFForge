(() => {
  const maxBytes = Number(window.PDFFORGE?.maxUploadSize || 50 * 1024 * 1024);
  const csrf = String(window.PDFForge?.csrfToken || "");
  const baseUrl = String(window.PDFForge?.baseUrl || "");

  document.querySelectorAll("[data-upload-form]").forEach((form) => {
    const picker = form.querySelector("[data-file-input]");
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
    let selectedFile = null;
    let jobId = "";
    let request = null;
    let processing = false;

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
      selectedFile = null;
      jobId = "";
      processing = false;
      picker.value = "";
      fileRow.hidden = true;
      statusBox.hidden = true;
      progress.hidden = false;
      progress.value = 0;
      retryButton.hidden = true;
      cancelButton.hidden = true;
      clearResults();
      uploadTitle.textContent = "Drag & drop your PDF here";
    };

    const showError = (message, canRetry = false) => {
      setStatus(message, true);
      progress.hidden = true;
      cancelButton.hidden = true;
      retryButton.hidden = !canRetry;
    };

    const chooseFile = async (file) => {
      reset();
      if (!file) return;
      if (!file.name.toLowerCase().endsWith(".pdf") || (file.type && file.type !== "application/pdf")) {
        showError("Choose a PDF file.", false);
        window.dispatchEvent(new CustomEvent("pdfforge:notice", { detail: { message: "Only PDF files are supported." } }));
        return;
      }
      if (file.size > maxBytes) {
        showError("This file exceeds the " + Math.round(maxBytes / 1024 / 1024) + " MB upload limit.", false);
        return;
      }
      if (file.size < 8) {
        showError("This file is too small to be a valid PDF.", false);
        return;
      }
      try {
        const signature = new TextDecoder().decode(await file.slice(0, 5).arrayBuffer());
        if (signature !== "%PDF-") {
          showError("This file does not look like a PDF document.", false);
          return;
        }
      } catch {
        showError("This browser could not validate the selected file.", false);
        return;
      }
      selectedFile = file;
      fileName.textContent = file.name;
      fileRow.hidden = false;
      uploadTitle.textContent = "Ready to convert";
      clearResults();
      retryButton.hidden = true;
      setStatus("Ready to upload.", false);
      progress.hidden = true;
    };

    const renderResult = (payload, completedJobId) => {
      statusText.classList.remove("danger-text");
      const isText = payload.tool === "pdf-to-text";
      const statusMessage = isText
        ? "Text extraction complete."
        : "Conversion complete. " + payload.page_count + (payload.page_count === 1 ? " page rendered." : " pages rendered.");
      setStatus(statusMessage);
      progress.hidden = true;
      retryButton.hidden = true;
      clearResults();
      const download = document.createElement("a");
      download.className = "button button-small";
      download.href = baseUrl + "/api/download.php?id=" + encodeURIComponent(completedJobId);
      download.textContent = isText ? "Download text" : payload.page_count > 1 ? "Download all pages" : "Download JPG";
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
      setStatus("Processing PDF pages. This may take a moment…");
      progress.hidden = true;
      retryButton.hidden = true;
      try {
        const body = new URLSearchParams({ id: currentJobId });
        const response = await fetch(baseUrl + "/api/process.php", {
          method: "POST",
          headers: { "X-CSRF-Token": csrf, "Content-Type": "application/x-www-form-urlencoded" },
          body,
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || "PDF conversion failed.");
        if (jobId === currentJobId) renderResult(payload, currentJobId);
      } catch (error) {
        if (jobId === currentJobId) showError(error.message || "PDF conversion failed.", true);
      } finally {
        if (jobId === currentJobId) processing = false;
      }
    };

    const startUpload = () => {
      if (!selectedFile) return;
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
      data.set("pdf", selectedFile);
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
    picker.addEventListener("change", () => chooseFile(picker.files?.[0]));
    ["dragenter", "dragover"].forEach((eventName) => zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.add("is-dragging");
    }));
    ["dragleave", "drop"].forEach((eventName) => zone.addEventListener(eventName, (event) => {
      event.preventDefault();
      zone.classList.remove("is-dragging");
    }));
    zone.addEventListener("drop", (event) => chooseFile(event.dataTransfer?.files?.[0]));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      startUpload();
    });
    zone.addEventListener("dblclick", startUpload);
    selectButton.insertAdjacentElement("afterend", Object.assign(document.createElement("button"), {
      type: "button",
      className: "button button-small convert-button",
      textContent: "Upload & convert",
    }));
    form.querySelector(".convert-button").addEventListener("click", startUpload);
    removeButton.addEventListener("click", reset);
    cancelButton.addEventListener("click", () => request?.abort());
    retryButton.addEventListener("click", () => {
      if (jobId) processJob();
      else startUpload();
    });
  });
})();
