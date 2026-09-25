<?php
/**
 * Add the standard IT request items once.
 *
 * This is intentionally an administrator-triggered action rather than an
 * automatic migration: catalogue content is customer-facing business data.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/services/forms.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
requireModuleAccessJson('forms');

try {
    $conn = connectToDatabase();
    $ctx = ActorContext::fromSession($conn);
    $items = [
        ['Battery AAA', 'Request AAA batteries for approved IT equipment.'],
        ['Computer Mouse', 'Request a replacement or additional computer mouse.'],
        ['Antivirus Software', 'Request antivirus software installation or renewal.'],
        ['Keyboard', 'Request a replacement or additional computer keyboard.'],
        ['Headset', 'Request a headset for calls, meetings, or support work.'],
        ['Webcam', 'Request a webcam for video calls and online meetings.'],
        ['Monitor', 'Request an additional or replacement computer monitor.'],
        ['Laptop Charger', 'Request a replacement or additional laptop charger.'],
        ['Laptop', 'Request a laptop for approved business use.'],
        ['Docking Station', 'Request a docking station for a compatible laptop.'],
        ['Power Adapter', 'Request a replacement or additional power adapter.'],
    ];
    $find = $conn->prepare('SELECT id FROM forms WHERE title = ? AND parent_form_id IS NULL LIMIT 1');
    $created = 0;

    foreach ($items as [$title, $description]) {
        $find->execute([$title]);
        if ($find->fetchColumn()) continue;

        FormsService::saveForm($conn, $ctx, [
            'title' => $title,
            'description' => $description,
            'is_active' => 1,
            'is_portal_visible' => 1,
            'fields' => [
                [
                    'field_type' => 'textarea',
                    'label' => 'Additional details',
                    'options' => '',
                    'is_required' => 0,
                ],
            ],
        ]);
        $created++;
    }

    echo json_encode(['success' => true, 'created' => $created]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
