<?php
$multiUpload = in_array($uploadTool ?? '', ['jpg-to-pdf', 'merge-pdf'], true);
$imageUpload = ($uploadTool ?? '') === 'jpg-to-pdf';
$acceptedTypes = $imageUpload ? 'image/jpeg,image/png,.jpg,.jpeg,.png' : 'application/pdf,.pdf';
$uploadTitleText = $imageUpload ? 'Drag & drop your images here' : 'Drag & drop your PDF here';
$selectionLabel = $imageUpload ? 'image files' : ($multiUpload ? 'PDF files' : 'a PDF file');
?>
<form class="upload-form" data-upload-form data-tool="<?= e($uploadTool ?? 'pdf-to-jpg') ?>" data-image-upload="<?= $imageUpload ? 'true' : 'false' ?>" action="<?= e(app_config('app_url')) ?>/api/upload.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="tool" value="<?= e($uploadTool ?? 'pdf-to-jpg') ?>">
    <input class="file-input" type="file" name="files[]" accept="<?= e($acceptedTypes) ?>" <?= $multiUpload ? 'multiple' : '' ?> data-file-input aria-label="Choose <?= e($selectionLabel) ?>">
    <div class="drop-zone" data-drop-zone tabindex="0" role="button" aria-label="Choose or drop <?= e($selectionLabel) ?>">
        <span class="drop-icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M12 5h16l9 9v29H12z"/><path d="M28 5v10h9M17 25h15M17 31h15M17 37h10"/></svg></span>
        <strong data-upload-title><?= e($uploadTitleText) ?></strong>
        <span class="upload-or">or</span>
        <button class="button button-upload" type="button" data-select-file><?= $imageUpload ? 'Select image files' : ($multiUpload ? 'Select files' : 'Select PDF file') ?></button>
        <span class="upload-limit"><?= $imageUpload ? 'JPG or PNG' : 'PDF' ?><?= $multiUpload ? ' · up to 20 files' : '' ?> · combined size up to <?= e(human_size((int) app_config('max_upload_size'))) ?></span>
    </div>
    <div class="upload-file" data-upload-file hidden><span class="file-badge">PDF</span><span class="file-name" data-file-name></span><button type="button" class="icon-button" data-remove-file aria-label="Remove selected file">×</button></div>
    <div class="upload-status" data-upload-status role="status" aria-live="polite" hidden>
        <div class="status-head"><span data-status-text>Uploading…</span><button type="button" class="text-button" data-cancel hidden>Cancel</button></div>
        <progress class="upload-progress" data-progress max="100" value="0" aria-label="Upload progress"></progress>
        <div class="result-actions" data-result hidden></div>
        <button class="button button-small upload-retry" type="button" data-retry hidden>Try again</button>
    </div>
    <?php if (($uploadTool ?? '') === 'split-pdf'): ?>
        <label class="tool-option">Pages to extract <input type="text" name="pages" placeholder="Leave blank to split every page, or enter 1-3,5"></label>
    <?php elseif (($uploadTool ?? '') === 'rotate-pdf'): ?>
        <label class="tool-option">Rotation <select name="degrees"><option value="90">90° clockwise</option><option value="180">180°</option><option value="270">270° clockwise</option></select></label>
        <label class="tool-option">Pages <input type="text" name="pages" placeholder="Leave blank for all, or enter 1-3,5"></label>
    <?php elseif (($uploadTool ?? '') === 'watermark-pdf'): ?>
        <label class="tool-option">Watermark text <input type="text" name="text" maxlength="100" value="CONFIDENTIAL" required></label>
    <?php elseif (($uploadTool ?? '') === 'compress-pdf'): ?>
        <label class="tool-option">Compression quality <select name="quality"><option value="screen">Smallest file</option><option value="ebook" selected>Balanced</option><option value="printer">Higher quality</option></select></label>
    <?php elseif (in_array($uploadTool ?? '', ['protect-pdf', 'unlock-pdf'], true)): ?>
        <label class="tool-option"><?= ($uploadTool ?? '') === 'protect-pdf' ? 'New PDF password' : 'Current PDF password' ?><input type="password" name="password" minlength="<?= ($uploadTool ?? '') === 'protect-pdf' ? '6' : '1' ?>" maxlength="128" autocomplete="new-password" required></label>
    <?php endif; ?>
</form>
