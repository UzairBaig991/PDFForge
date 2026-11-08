<form class="upload-form" data-upload-form action="<?= e(app_config('app_url')) ?>/api/upload.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="tool" value="<?= e($uploadTool ?? 'pdf-to-jpg') ?>">
    <input class="file-input" type="file" name="pdf" accept="application/pdf,.pdf" data-file-input aria-label="Choose a PDF file">
    <div class="drop-zone" data-drop-zone tabindex="0" role="button" aria-label="Choose or drop a PDF file">
        <span class="drop-icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M12 5h16l9 9v29H12z"/><path d="M28 5v10h9M17 25h15M17 31h15M17 37h10"/></svg></span>
        <strong data-upload-title>Drag &amp; drop your PDF here</strong>
        <span class="upload-or">or</span>
        <button class="button button-upload" type="button" data-select-file>Select PDF file</button>
        <span class="upload-limit">PDF · up to <?= e(human_size((int) app_config('max_upload_size'))) ?></span>
    </div>
    <div class="upload-file" data-upload-file hidden><span class="file-badge">PDF</span><span class="file-name" data-file-name></span><button type="button" class="icon-button" data-remove-file aria-label="Remove selected file">×</button></div>
    <div class="upload-status" data-upload-status role="status" aria-live="polite" hidden>
        <div class="status-head"><span data-status-text>Uploading…</span><button type="button" class="text-button" data-cancel hidden>Cancel</button></div>
        <progress class="upload-progress" data-progress max="100" value="0" aria-label="Upload progress"></progress>
        <div class="result-actions" data-result hidden></div>
        <button class="button button-small upload-retry" type="button" data-retry hidden>Try again</button>
    </div>
</form>
