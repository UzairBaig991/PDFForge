<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
json_response([
    'success' => true,
    'tools' => [
        ['slug' => 'pdf-to-jpg', 'name' => 'PDF to JPG', 'category' => 'Convert PDF', 'status' => 'ready'],
        ['slug' => 'jpg-to-pdf', 'name' => 'JPG to PDF', 'category' => 'Convert PDF', 'status' => 'coming_soon'],
        ['slug' => 'pdf-to-word', 'name' => 'PDF to Word', 'category' => 'Convert PDF', 'status' => 'coming_soon'],
        ['slug' => 'pdf-to-excel', 'name' => 'PDF to Excel', 'category' => 'Convert PDF', 'status' => 'coming_soon'],
        ['slug' => 'pdf-to-ppt', 'name' => 'PDF to PowerPoint', 'category' => 'Convert PDF', 'status' => 'coming_soon'],
        ['slug' => 'pdf-to-text', 'name' => 'PDF to Text', 'category' => 'Convert PDF', 'status' => 'ready'],
        ['slug' => 'merge-pdf', 'name' => 'Merge PDF', 'category' => 'Organize PDF', 'status' => 'coming_soon'],
        ['slug' => 'split-pdf', 'name' => 'Split PDF', 'category' => 'Organize PDF', 'status' => 'coming_soon'],
        ['slug' => 'compress-pdf', 'name' => 'Compress PDF', 'category' => 'Optimize PDF', 'status' => 'coming_soon'],
        ['slug' => 'rotate-pdf', 'name' => 'Rotate PDF', 'category' => 'Organize PDF', 'status' => 'coming_soon'],
        ['slug' => 'watermark-pdf', 'name' => 'Watermark PDF', 'category' => 'Edit PDF', 'status' => 'coming_soon'],
        ['slug' => 'protect-pdf', 'name' => 'Protect PDF', 'category' => 'Security', 'status' => 'coming_soon'],
        ['slug' => 'unlock-pdf', 'name' => 'Unlock PDF', 'category' => 'Security', 'status' => 'coming_soon'],
    ],
]);
